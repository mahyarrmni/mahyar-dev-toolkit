# Algorithms and mathematical assumptions

## 1. Warehouse geometry (`wdt/model.py`)
Single-block, parallel-aisle warehouse. Aisle length is `L`; cross-aisles run along `y = 0` (front, where the depot is) and
`y = L` (back). Each storage location `l` has an *access point* `(x_l, y_l)` on its aisle centre-line (levels and the two
rack sides share it). Travel is rectilinear along the aisle network:

| pair | distance |
|---|---|
| same aisle | `|y_i - y_j|` |
| different aisles | `min(y_i + y_j, 2L - y_i - y_j) + |x_i - x_j|` (front or back cross-aisle) |
| depot -> location | `|x_l - x_depot| + y_l` |

These are shortest-path lengths in a graph, so they are symmetric and satisfy the triangle inequality (tested). `Warehouse.path`
returns the polyline whose length equals the distance (tested), which the 2D map uses to draw routes.

## 2. Decision problem
SKUs `i = 1..S`, locations `l = 1..L`, `S <= L`. Variable `l(i)` = location of SKU `i` (injective: one SKU per location).

Hard constraints (feasibility matrix `F[i,l]`):
* volume: `qty_i * vol_i / fill <= cap_vol(l)` (`fill` = usable share of a location, default 0.75),
* weight: `qty_i * w_i <= cap_wgt(l)`,
* storage class: class of SKU `i` (general / cold / hazmat) equals zone of `l`.

Demand comes from the order history: `f_i` = number of order lines of SKU `i`, `a_ij` = number of orders containing both
`i` and `j` (the 3 000 largest pairs are kept).

## 3. Objectives (`wdt/objective.py`) - all minimised
| | definition | unit |
|---|---|---|
| distance | `Z1 = sum_i 2 f_i d0(l(i))`, `d0` = depot distance | m |
| affinity | `Z2 = sum_(i,j) a_ij d(l(i), l(j))` | m |
| ergonomics | `Z3 = sum_i f_i h(l(i)) (1 + w_i / 10 kg)`, `h = 4 s * max(level - 1, 0)` (levels 0-based; floor and chest height are free) | s |
| slot fit | `Z4 = sum_i (cap_vol(l(i)) - footprint_i)` (unused volume in occupied slots; keeps big slots for big SKUs) | m3 |

Scalarisation: `J = sum_k w_k Z_k / Zref_k + M * V`, with weights normalised to sum 1, `Zref_k` = value of the baseline layout
(so `J(baseline) = 1` and `J = 0.54` means 46 % better on the weighted, normalised scale), `V` = number of violated
constraints (incl. duplicated locations) and `M = 10`. `pareto_sweep` solves several weight vectors and returns the
non-dominated set.

`Z1` is a proxy (it treats every line as its own trip). Real performance is measured afterwards with actual tours (section 6)
and the simulation (section 7).

## 4. Exact solution of the linear part (OR-Tools)
Without `Z2`, the problem `min sum_i C[i, l(i)]` is a rectangular assignment problem. It is solved **exactly** as a
min-cost flow (source -> SKU -> feasible location -> sink, unit capacities, integer-scaled costs) with
`ortools.graph.min_cost_flow`; the constraint matrix is totally unimodular, so the LP optimum is integral. Its value `LB` is a
valid lower bound on `J` (because `Z2 >= 0` and penalties are `>= 0`); the reported *gap* `(J - LB) / J` is therefore an upper
bound on the distance to optimality, loose when the affinity weight is large. The exact solution also seeds the GA and
guarantees the result is never worse than it.

## 5. Genetic algorithm (`wdt/optimize.py`)
* **Encoding**: permutation of all `L` locations; SKU `i` occupies position `i < S`, the remaining positions are spare slots.
  Uniqueness is automatic.
* **Initial population**: exact seed, baseline, perturbations of the seed (feasibility-preserving swaps) and 25 % random feasible
  constructions (most-constrained SKU first).
* **Selection**: tournament (k = 3), elitism (3).
* **Crossover**: position-based swap crossover - take a random half of the positions from parent B and repair the permutation by
  swapping (already inherited positions are locked).
* **Mutation**: move a random SKU to a random *feasible* location, swapping with the occupant if the occupant stays feasible.
* **Repair**: infeasible genes are swapped to feasible locations; the penalty `M*V` handles the rest.
* **Memetic local search**: every 25 generations and at the end, batches of 48 single feasible swaps are evaluated with the exact
  objective; improving moves are accepted.
* **Evaluation** is fully vectorised in NumPy: `O(P (S + K))` per generation (`K` = number of affinity pairs).
* **Stopping**: generation limit or `patience` generations without improvement. Seeded RNG => deterministic.
* **Safeguard**: if the final solution were infeasible or worse than the exact seed, the seed is returned.

Memory: the three `S x L` cost matrices are dense (`~ 24 S L` bytes); ~5 000 x 5 000 needs ~600 MB.

## 6. Picking tours and picking time (`wdt/routing.py`)
Each order is a TSP from the depot through its distinct access points and back. Orders with <= 2 stops are solved exactly,
larger ones with OR-Tools routing (`PATH_CHEAPEST_ARC` + default local search; near-optimal for small tours - the tests compare
against brute force for 3-6 stops). Time per order:

`t = setup + distance / speed + sum_lines (pick + unit * qty + h(level) * (1 + w / 10))`

with defaults 30 s, 1.1 m/s, 10 s, 1.5 s. One picker handles one order (discrete picking).

## 7. Discrete-event simulation (`wdt/sim.py`, SimPy)
* Orders arrive as a Poisson process (rate `lambda`), sampled with replacement from the order history; the same seed gives the same
  stream for both layouts (common random numbers), and `lambda` is derived from the **baseline** layout (`auto_rate`:
  `target_util * pickers * 3600 / mean_service_time`) so both layouts face identical demand.
* `pickers` workers pull orders FIFO, walk the tour, and at each stop occupy one place of that aisle's resource
  (capacity = `aisle_capacity`): blocking is modelled at pick stops, not while walking.
* After picking, the order queues for `packers` stations (service `base + per_line * lines`).
* Outputs: cycle time (arrival -> packed), waits per stage, throughput, per-resource utilisation = busy time / (capacity x makespan),
  sampled queue lengths.

## 8. Capacity and bottlenecks (`wdt/analysis.py`)
* Slot, cube and weight utilisation by zone and level; ABC classes by cumulative share of lines (A <= 80 %, B <= 95 %).
* Aisle *load index* = share of picks x number of aisles (1 = balanced). Index >= 1.5 is flagged as a hot aisle - an expected
  trade-off of distance minimisation, since fast movers cluster near the depot.
* Resource findings: utilisation >= 85 % high, >= 70 % medium; aisle blocking >= 10 s (medium) / 30 s (high) per visit.
* Stage capacity (`servers * 3600 / mean service time`) and staffing by Little's law: `pickers = lambda * E[service] / (3600 * rho)`.

## 9. Assumptions and limitations
Stationary demand; one SKU per location; no replenishment, receiving or put-away flows; no congestion while walking; no vertical
travel beyond the handling penalty; single block layout (no mid-aisle cross-aisles); no batching/zone/wave picking. Results are
model outputs: calibrate speeds, times and capacities to your site before drawing operational conclusions.

## 10. Verification (`tests/`)
Brute-force enumeration confirms the exact assignment and the GA optimum on small instances; metric properties of distances;
route polylines match distances; TSP vs brute force; the simulation reproduces a hand-computed single-order cycle time, conserves
orders and ordering of timestamps, is deterministic and responds correctly to staffing and aisle capacity; Persian headers/digits,
cp1256 encoding, constraint satisfaction on the sample data, report/Excel export and a headless end-to-end run of the Streamlit app.

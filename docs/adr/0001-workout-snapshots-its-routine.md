# A Workout snapshots its Routine at start

Starting a Workout from a Routine copies the Routine's exercises and per-set targets into the Workout. From then on the Workout is independent: it can deviate freely, and later edits to the Routine (or archiving it) never rewrite it. We chose this over a live reference to the Routine plus stored overrides, because history must stay true to what was planned and done on the day, and because in-gym swaps, extra sets and skipped exercises would otherwise become awkward override records. The cost is that the Routine's structure is duplicated in every Workout. That is deliberate, not a normalisation oversight.

## Consequences

- Changes flow from a Workout back into its Routine only through an explicit "update Routine from this Workout" action, never automatically.
- A Workout keeps a link to the Routine it was started from (Routines are archived, not deleted), but it never reads its plan through that link.

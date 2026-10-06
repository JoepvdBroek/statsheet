# Statsheet

A personal strength-training tracker: plan reusable Routines, log the Workouts you perform, and follow weekly volume per Muscle against goals.

## Language

### Planning and performing

**Routine**:
A reusable, named plan of exercises, each with per-set targets (reps and weight), e.g. "Push Day".
_Avoid_: Template, workout plan, program

**Workout**:
One dated performance of training. It is started from a Routine (whose exercises and targets are copied into it at that moment) or started empty. It never changes when its Routine changes later.
_Avoid_: Session, workout session, training

**Workout Note**:
Optional free text on a Workout giving context the numbers don't show (deload, injury, bad sleep).

**Archived Routine**:
A Routine taken out of use. It is hidden from planning but restorable, and the Workouts started from it still point to it.
_Avoid_: Deleted routine

**Last Done**:
The start date of the most recent finished Workout started from a Routine. A Routine never performed has none. A Workout in progress does not count.
_Avoid_: Last performed, last used

**Set**:
One planned or performed round of an exercise within a Workout, recording a **Target** and an **Actual**. A set without an Actual is *not done*.

**Target**:
The reps × weight a set aims for. In a Routine it is the plan; in a Workout it is what was pre-filled when the Workout started.

**Actual**:
The reps × weight actually performed in a set of a Workout. A set *meets its target* when both its reps and its weight are at or above the Target.

**Pre-fill**:
The Targets a new Workout gets at start, per set position, from the most recent Workout containing that exercise. That set's Actual is used if it met its target (or had no Target); otherwise that set's Target carries over; with no such earlier set, the Routine's Target is used.
_Avoid_: Auto-fill, suggestion

### Exercises and muscles

**Exercise**:
A specific movement, variation included (barbell and dumbbell bench press are two Exercises), with at least one primary Muscle and optionally secondary Muscles. Seeded from a public catalogue or created by the owner; either way the owner's to edit.
_Avoid_: Movement, lift

**Bodyweight Exercise**:
An Exercise whose load is mainly the lifter's own body (pull-up, dip). The weight of its sets is the *added* load, 0 when none.
_Avoid_: Body only

**Muscle**:
One of 17 fixed body areas an Exercise trains, as primary or secondary: abdominals, abductors, adductors, biceps, calves, chest, forearms, glutes, hamstrings, lats, lower back, middle back, neck, quadriceps, shoulders, traps, triceps. The only level volume and goals are tracked on.
_Avoid_: Muscle group, body part

### Volume, goals and records

**Warm-up Set**:
A Set marked as preparation. It is logged but never counts toward Volume, Goals or Personal Records.

**Bodyweight**:
The owner's single current body weight, copied into each Workout when it starts. It adds to the load of Bodyweight Exercise sets. It is not a measurement history.
_Avoid_: Body measurement

**Volume**:
Tonnage (reps × weight, in kg) of done, non-warm-up Sets, attributed to Muscles: in full to each primary Muscle and half to each secondary Muscle. For a Bodyweight Exercise the weight is Bodyweight plus added load.
_Avoid_: Load, workload, training volume (as a set count)

**Week**:
Monday to Sunday in the owner's timezone. A Workout belongs to the Week (and calendar month) of its start date.

**Goal**:
An optional weekly minimum Volume for one Muscle, in force from the Week it is set. Each Week is judged against the Goal in force then.
_Avoid_: Target (that is a Set's planned reps × weight)

**Personal Record**:
The best non-warm-up performance of one Exercise by one measure: heaviest weight, best Estimated 1RM, most reps at a weight, or best set tonnage. For a Bodyweight Exercise, heaviest and reps-at-weight use the added load; the others use Bodyweight plus added load.
_Avoid_: PB, max

**Estimated 1RM**:
A set's weight × (1 + reps / 30) (Epley), computed for sets of at most 12 reps.
_Avoid_: 1RM, max

### AI

**Weekly Review**:
An AI-written review of one Week: an overall summary, a note per Muscle with a Goal, and advice for the next Week. It is text only, one per Week (regenerating replaces it), and never changes the owner's data.
_Avoid_: Report, coach feedback

**Review Rating**:
The owner's optional thumbs up/down, with an optional comment, on a Weekly Review. It is used to judge whether the reviews are any good.

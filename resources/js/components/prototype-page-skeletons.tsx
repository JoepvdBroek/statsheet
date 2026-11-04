/**
 * PROTOTYPE (throwaway) for the wayfinder ticket "What does each page's skeleton look like?".
 * Three skeleton styles for the target page of a page change, picked from the floating switcher:
 * A Faithful (mirrors each page, all grey), B Archetype (three shared shapes), C Static text real
 * (mirrors each page, static headings/labels/buttons as real text, only data spots grey).
 * Skeleton blocks fade in after the switcher's delay; the header shows immediately.
 * Lives on the `prototype/page-skeletons` branch only; never merge it.
 */
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { CSSProperties, ReactNode } from 'react';
import { createContext, useContext } from 'react';
import { cn } from '@/lib/utils';

type Style = 'A' | 'B' | 'C';
const Ctx = createContext<{ style: Style; delay: number }>({
    style: 'A',
    delay: 150,
});

/** A grey block that fades in after the delay. */
function Bar({
    className,
    style,
}: {
    className?: string;
    style?: CSSProperties;
}) {
    const { delay } = useContext(Ctx);

    return (
        <div
            className={cn('rounded-md bg-accent', className)}
            style={{
                animation: delay
                    ? `proto-skeleton-in 180ms ease-out ${delay}ms both, proto-pulse 1.6s ease-in-out ${delay + 180}ms infinite`
                    : 'proto-pulse 1.6s ease-in-out infinite',
                ...style,
            }}
        />
    );
}

/** Static text: real in style C, a grey bar of the given width otherwise. */
function Txt({
    children,
    w,
    className,
    bar = 'h-4',
}: {
    children: ReactNode;
    w: string;
    className?: string;
    bar?: string;
}) {
    const { style } = useContext(Ctx);

    return style === 'C' ? (
        <span className={className}>{children}</span>
    ) : (
        <Bar className={cn(bar, w)} />
    );
}

/** A static button: real outline with label in C, a grey pill otherwise. */
function Btn({
    children,
    w,
    className,
}: {
    children: ReactNode;
    w: string;
    className?: string;
}) {
    const { style } = useContext(Ctx);

    return style === 'C' ? (
        <span
            className={cn(
                'inline-flex items-center justify-center rounded-full border px-3 text-sm font-medium text-muted-foreground',
                className,
            )}
        >
            {children}
        </span>
    ) : (
        <Bar className={cn('rounded-full', w, className)} />
    );
}

const Label = ({ children, w }: { children: ReactNode; w: string }) => (
    <Txt
        w={w}
        bar="h-3"
        className="text-[11px] tracking-wider text-muted-foreground uppercase"
    >
        {children}
    </Txt>
);
const Card = ({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) => (
    <div className={cn('flex flex-col rounded-xl border bg-card', className)}>
        {children}
    </div>
);
const Pills = ({ n, h = 'h-6' }: { n: number; h?: string }) => (
    <div className="flex flex-wrap gap-1.5">
        {Array.from({ length: n }, (_, i) => (
            <Bar key={i} className={cn('w-16 rounded-full', h)} />
        ))}
    </div>
);
const times = (n: number, render: (i: number) => ReactNode) =>
    Array.from({ length: n }, (_, i) => render(i));

function Stat({ label }: { label: string }) {
    return (
        <div className="flex flex-col gap-1">
            <Bar className="h-8 w-16" />
            <Label w="w-14">{label}</Label>
        </div>
    );
}

function ExerciseCardSkeleton({ planning = false }: { planning?: boolean }) {
    return (
        <Card className="gap-3 px-3 py-4">
            <div className="flex items-start gap-3 px-1">
                <div className="flex flex-1 flex-col gap-2">
                    <Bar className="h-5 w-1/2" />
                    <Pills n={2} />
                </div>
                <Bar className="size-10 rounded-full" />
            </div>
            <div className="flex gap-4 px-1">
                {(planning
                    ? ['Set', 'kg', 'Reps', 'Warm']
                    : ['Set', 'Target', 'kg', 'Reps']
                ).map((label) => (
                    <Label key={label} w="w-8">
                        {label}
                    </Label>
                ))}
            </div>
            {times(3, (i) => (
                <div
                    key={i}
                    className="grid h-12 grid-cols-[2rem_minmax(0,1fr)_4.5rem_3.75rem_2.5rem] items-center gap-2 px-1"
                >
                    <Bar className="size-6" />
                    <Bar className="h-4 w-20" />
                    <Bar className="h-10" />
                    <Bar className="h-10" />
                    <Bar className="size-10 rounded-full" />
                </div>
            ))}
            <Btn w="w-full" className="h-10 w-full">
                Add set
            </Btn>
        </Card>
    );
}

function RoutineCardSkeleton() {
    return (
        <Card className="gap-4 p-5">
            <div className="flex flex-col gap-2">
                <Bar className="h-5 w-1/2" />
                <Bar className="h-4 w-2/3" />
            </div>
            <Pills n={3} />
            <div className="flex gap-2">
                <Btn w="w-28" className="h-8">
                    Start workout
                </Btn>
                <Btn w="w-14" className="h-8 border-transparent">
                    Edit
                </Btn>
            </div>
        </Card>
    );
}

const RowCard = ({ pills = false }: { pills?: boolean }) => (
    <Card className="gap-2 px-4 py-3">
        <Bar className="h-5 w-40" />
        {pills ? <Pills n={2} /> : <Bar className="h-4 w-24" />}
    </Card>
);

const StartEmpty = () => (
    <Btn w="w-full" className="h-11 w-full border-dashed">
        Start an empty workout
    </Btn>
);

const Description = ({
    children,
    w = 'w-3/4',
}: {
    children?: ReactNode;
    w?: string;
}) =>
    children ? (
        <Txt w={w} className="text-sm text-muted-foreground">
            {children}
        </Txt>
    ) : (
        <Bar className={cn('h-4', w)} />
    );

/** Faithful per-page bodies (style A and C). */
const PAGES: Record<string, (ctx: { inProgress: boolean }) => ReactNode> = {
    home: ({ inProgress }) => (
        <div className="flex flex-col gap-4 p-4">
            {inProgress ? (
                <Card className="flex-row items-center justify-between gap-4 border-primary/40 p-4">
                    <div className="flex flex-col gap-1.5">
                        <Label w="w-28">Workout in progress</Label>
                        <Bar className="h-5 w-32" />
                        <Bar className="h-4 w-40" />
                    </div>
                    <Btn w="w-20" className="h-9">
                        Resume
                    </Btn>
                </Card>
            ) : null}
            <Card className="grid grid-cols-2 gap-4 p-4">
                <Stat label="This week" />
                <Stat label="Volume" />
            </Card>
            <div className="flex flex-col gap-3">
                {times(3, (i) => (
                    <RoutineCardSkeleton key={i} />
                ))}
            </div>
            <StartEmpty />
        </div>
    ),
    workoutShow: ({ inProgress }) => (
        <div className="flex flex-col gap-6 p-4">
            <div className="flex items-start justify-between gap-4">
                <Bar className="h-4 w-2/3" />
                {inProgress ? (
                    <Btn w="w-32" className="h-8">
                        Workout history
                    </Btn>
                ) : null}
            </div>
            <div className="flex flex-col gap-3">
                {times(3, (i) => (
                    <ExerciseCardSkeleton key={i} />
                ))}
                <Btn w="w-full" className="h-10 w-full">
                    Add Exercise
                </Btn>
            </div>
            {inProgress ? (
                <div className="sticky bottom-(--tab-bar-height) -mx-4 border-t bg-background/95 px-4 py-3">
                    <Btn w="w-full" className="h-12 w-full">
                        Finish workout
                    </Btn>
                </div>
            ) : null}
        </div>
    ),
    workoutIndex: () => (
        <div className="flex flex-col gap-6 p-4">
            <StartEmpty />
            <Description>Every Workout you logged, newest first.</Description>
            <div className="flex flex-col gap-2">
                {times(6, (i) => (
                    <RowCard key={i} />
                ))}
            </div>
        </div>
    ),
    exerciseIndex: () => (
        <div className="flex flex-col gap-6 p-4">
            <div className="flex items-start justify-between gap-3">
                <Description>
                    Search by name, filter by Muscle or equipment.
                </Description>
                <Btn w="w-16" className="h-9">
                    New
                </Btn>
            </div>
            <div className="grid gap-3">
                <Bar className="h-9" />
                <Bar className="h-9" />
                <Bar className="h-9" />
            </div>
            <Btn w="w-40" className="h-10">
                In use · Archived
            </Btn>
            <div className="flex flex-col gap-2">
                {times(6, (i) => (
                    <RowCard key={i} pills />
                ))}
            </div>
        </div>
    ),
    exerciseShow: () => (
        <div className="flex flex-col gap-6 p-4">
            <div className="flex items-start justify-between gap-3">
                <Bar className="h-4 w-2/3" />
                <Btn w="w-14" className="h-8">
                    Edit
                </Btn>
            </div>
            <div className="-mt-4">
                <Pills n={3} />
            </div>
            <Card className="gap-4 p-4">
                <Txt w="w-36" className="font-medium">
                    Personal Records
                </Txt>
                <div className="grid grid-cols-2 gap-4">
                    <Stat label="Heaviest" />
                    <Stat label="Est. 1RM" />
                    <Stat label="Best set" />
                </div>
                <Label w="w-36">Most reps at a weight</Label>
                <Pills n={4} h="h-7" />
            </Card>
            <Card className="gap-6 p-4">
                {[
                    'Best Est. 1RM per Workout',
                    'Heaviest weight per Workout',
                ].map((caption) => (
                    <div key={caption} className="flex flex-col gap-2">
                        <Label w="w-40">{caption}</Label>
                        <Bar className="h-[200px]" />
                    </div>
                ))}
            </Card>
            <div className="flex flex-col">
                <Txt w="w-44" className="font-medium">
                    Recent performances
                </Txt>
                {times(3, (i) => (
                    <div
                        key={i}
                        className="flex flex-col gap-2 border-b py-4 last:border-b-0"
                    >
                        <Bar className="h-4 w-1/2" />
                        <Pills n={3} h="h-7" />
                    </div>
                ))}
            </div>
        </div>
    ),
    statsHub: () => (
        <div className="flex flex-col gap-4 p-4">
            <Card className="gap-4 p-4">
                <div className="flex items-start justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <Txt w="w-24" className="font-medium">
                            This Week
                        </Txt>
                        <Bar className="h-4 w-56" />
                    </div>
                    <Btn w="w-16" className="h-8">
                        Goals
                    </Btn>
                </div>
                <div className="grid grid-cols-2 gap-x-4 gap-y-5">
                    {times(6, (i) => (
                        <div key={i} className="flex flex-col gap-1">
                            <Bar className="h-8 w-20" />
                            <Bar className="h-3 w-16" />
                        </div>
                    ))}
                </div>
            </Card>
            <Card className="gap-4 p-4">
                <div className="flex items-start justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <Txt w="w-32" className="font-medium">
                            Weekly Review
                        </Txt>
                        <Description w="w-56">
                            An AI-written look at a Week of your log.
                        </Description>
                    </div>
                    <Btn w="w-24" className="h-8">
                        All reviews
                    </Btn>
                </div>
                <Bar className="h-16 rounded-lg" />
            </Card>
            <ul className="divide-y overflow-hidden rounded-xl border bg-card">
                {[
                    'Weekly trend',
                    'Monthly summary',
                    'Exercises',
                    'Weekly Reviews',
                    'Goals',
                ].map((title) => (
                    <li
                        key={title}
                        className="flex min-h-14 items-center gap-3 px-4 py-3"
                    >
                        <Bar className="size-5" />
                        <Txt w="w-32" className="flex-1 text-sm">
                            {title}
                        </Txt>
                        <ChevronRight className="size-4 text-muted-foreground" />
                    </li>
                ))}
            </ul>
        </div>
    ),
    routineForm: () => (
        <div className="flex flex-col gap-6 p-4">
            <Bar className="h-4 w-1/2" />
            <div className="grid gap-2">
                <Txt w="w-12" className="text-sm font-medium">
                    Name
                </Txt>
                <Bar className="h-9" />
            </div>
            <div className="flex flex-col gap-3">
                {times(3, (i) => (
                    <ExerciseCardSkeleton key={i} planning />
                ))}
            </div>
            <Btn w="w-full" className="h-10 w-full">
                Add Exercise
            </Btn>
            <Btn w="w-24" className="h-10 self-start">
                Save
            </Btn>
        </div>
    ),
    reviewIndex: () => (
        <div className="flex flex-col gap-6 p-4">
            <Description>
                Reread earlier reviews and their advice, or ask for another
                Week.
            </Description>
            <div className="flex flex-col gap-2">
                <Label w="w-24">A day in the Week</Label>
                <div className="flex gap-2">
                    <Bar className="h-9 w-44" />
                    <Btn w="w-32" className="h-9">
                        Ask for a review
                    </Btn>
                </div>
            </div>
            <div className="flex flex-col gap-2">
                {times(4, (i) => (
                    <Bar key={i} className="h-20 rounded-lg" />
                ))}
            </div>
        </div>
    ),
};

/** Style B: three shared shapes. */
const ARCHETYPES = {
    list: () => (
        <div className="flex flex-col gap-4 p-4">
            <Bar className="h-4 w-2/3" />
            {times(7, (i) => (
                <Bar key={i} className="h-16 rounded-xl" />
            ))}
        </div>
    ),
    cards: () => (
        <div className="flex flex-col gap-4 p-4">
            <Bar className="h-4 w-2/3" />
            {times(3, (i) => (
                <Bar key={i} className="h-44 rounded-xl" />
            ))}
        </div>
    ),
    detail: () => (
        <div className="flex flex-col gap-4 p-4">
            <Bar className="h-28 rounded-xl" />
            <Bar className="h-60 rounded-xl" />
            {times(3, (i) => (
                <Bar key={i} className="h-14 rounded-xl" />
            ))}
        </div>
    ),
};

type Route = {
    title: string | null;
    parent?: string;
    page?: keyof typeof PAGES;
    archetype: keyof typeof ARCHETYPES;
};

function routeFor(path: string, inProgress: boolean): Route {
    const table: [RegExp, Route][] = [
        [/^\/$/, { title: 'Routines', page: 'home', archetype: 'cards' }],
        [
            /^\/workouts$/,
            {
                title: 'Workout history',
                page: 'workoutIndex',
                archetype: 'list',
            },
        ],
        [
            /^\/workouts\/(\d+|in-progress)$/,
            inProgress
                ? {
                      title: 'Workout in progress',
                      page: 'workoutShow',
                      archetype: 'cards',
                  }
                : {
                      title: 'Workout',
                      parent: 'Workout history',
                      page: 'workoutShow',
                      archetype: 'cards',
                  },
        ],
        [
            /^\/routines\/create$/,
            {
                title: 'New Routine',
                parent: 'Routines',
                page: 'routineForm',
                archetype: 'cards',
            },
        ],
        [
            /^\/routines\/\d+\/edit$/,
            {
                title: 'Edit Routine',
                parent: 'Routines',
                page: 'routineForm',
                archetype: 'cards',
            },
        ],
        [
            /^\/exercises$/,
            {
                title: 'Exercises',
                parent: 'Stats',
                page: 'exerciseIndex',
                archetype: 'list',
            },
        ],
        [
            /^\/exercises\/create$/,
            { title: 'New Exercise', parent: 'Exercises', archetype: 'cards' },
        ],
        [
            /^\/exercises\/\d+\/edit$/,
            { title: 'Edit Exercise', parent: 'Exercises', archetype: 'cards' },
        ],
        [
            /^\/exercises\/\d+$/,
            {
                title: null,
                parent: 'Exercises',
                page: 'exerciseShow',
                archetype: 'detail',
            },
        ],
        [
            /^\/statistics$/,
            { title: 'Stats', page: 'statsHub', archetype: 'detail' },
        ],
        [
            /^\/statistics\/weekly-trend$/,
            { title: 'Weekly trend', parent: 'Stats', archetype: 'detail' },
        ],
        [
            /^\/statistics\/monthly-summary$/,
            { title: 'Monthly summary', parent: 'Stats', archetype: 'detail' },
        ],
        [/^\/goals$/, { title: 'Goals', parent: 'Stats', archetype: 'list' }],
        [
            /^\/reviews$/,
            {
                title: 'Weekly Reviews',
                parent: 'Stats',
                page: 'reviewIndex',
                archetype: 'list',
            },
        ],
        [
            /^\/reviews\/\d+$/,
            {
                title: 'Weekly Review',
                parent: 'Weekly Reviews',
                archetype: 'detail',
            },
        ],
        [
            /^\/settings/,
            { title: 'Settings', parent: 'Routines', archetype: 'cards' },
        ],
    ];

    return (
        table.find(([pattern]) => pattern.test(path))?.[1] ?? {
            title: null,
            archetype: 'list',
        }
    );
}

/** The target page's header (immediate) and body skeleton (fades in). */
export function PrototypeSkeletonPage({
    path,
    style,
    delay,
    workoutInProgressId,
}: {
    path: string;
    style: Style;
    delay: number;
    workoutInProgressId: number | null;
}) {
    const inProgress =
        path === '/workouts/in-progress' ||
        (workoutInProgressId !== null &&
            path === `/workouts/${workoutInProgressId}`);
    const route = routeFor(path, inProgress);
    const body =
        style !== 'B' && route.page
            ? PAGES[route.page]({ inProgress })
            : ARCHETYPES[route.archetype]();

    return (
        <Ctx.Provider value={{ style, delay }}>
            <style>{`@keyframes proto-skeleton-in { from { opacity: 0 } to { opacity: 1 } } @keyframes proto-pulse { 50% { opacity: .55 } }`}</style>
            <div aria-busy="true">
                <header className="flex flex-col gap-1 px-4 pt-[calc(env(safe-area-inset-top)+1.5rem)]">
                    {route.parent ? (
                        <div className="flex items-center gap-1">
                            <span className="-ml-2.5 inline-flex size-11 items-center justify-center">
                                <ChevronLeft
                                    aria-hidden="true"
                                    className="size-[22px]"
                                />
                            </span>
                            <span className="text-sm text-muted-foreground">
                                {route.parent}
                            </span>
                        </div>
                    ) : null}
                    {route.title ? (
                        <h1 className="text-2xl leading-[1.4] font-normal tracking-[0.04em]">
                            {route.title}
                        </h1>
                    ) : (
                        <Bar className="my-1.5 h-7 w-1/2" />
                    )}
                </header>
                {body}
            </div>
        </Ctx.Provider>
    );
}

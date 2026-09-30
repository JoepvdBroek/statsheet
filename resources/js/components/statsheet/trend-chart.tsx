import * as React from 'react';

import { cn } from '@/lib/utils';

type TrendPoint = {
    /** Short axis label ("22 Sep"). */
    label: string;
    value: number;
    /** This point set a Personal Record: drawn as a ringed dot. */
    pr?: boolean;
};

type TrendChartProps = Omit<React.ComponentProps<'figure'>, 'title'> & {
    points: TrendPoint[];
    /** Unit shown after values ("kg"). */
    unit?: string;
    /** Accessible name; also the visible caption unless hideCaption. */
    title: string;
    hideCaption?: boolean;
    height?: number;
    /** Decimal places in labels. */
    digits?: number;
};

function niceTicks(min: number, max: number, count = 4) {
    const span = max - min || 1;
    const raw = span / (count - 1);
    const mag = 10 ** Math.floor(Math.log10(raw));
    const step =
        [1, 2, 2.5, 5, 10].map((m) => m * mag).find((s) => s >= raw) ?? raw;
    const lo = Math.floor(min / step) * step;
    const hi = Math.ceil(max / step) * step;
    const ticks: number[] = [];
    for (let v = lo; v <= hi + step / 2; v += step)
        ticks.push(Number(v.toFixed(6)));
    return ticks;
}

function TrendChart({
    points,
    unit = '',
    title,
    hideCaption = false,
    height = 200,
    digits = 1,
    className,
    ...props
}: TrendChartProps) {
    const ref = React.useRef<HTMLDivElement>(null);
    const [width, setWidth] = React.useState(320);
    const [hover, setHover] = React.useState<number | null>(null);

    React.useEffect(() => {
        const el = ref.current;
        if (!el) return;
        const ro = new ResizeObserver(([e]) =>
            setWidth(Math.max(200, e.contentRect.width)),
        );
        ro.observe(el);
        return () => ro.disconnect();
    }, []);

    const fmt = (v: number) =>
        v.toLocaleString('en-GB', { maximumFractionDigits: digits });

    if (!points.length) {
        return (
            <figure
                className={cn('text-sm text-muted-foreground', className)}
                {...props}
            >
                No sets yet.
            </figure>
        );
    }

    const pad = { top: 16, right: 48, bottom: 24, left: 36 };
    const values = points.map((p) => p.value);
    const ticks = niceTicks(Math.min(...values), Math.max(...values));
    const yMin = ticks[0];
    const yMax = ticks[ticks.length - 1];
    const iw = width - pad.left - pad.right;
    const ih = height - pad.top - pad.bottom;
    const x = (i: number) =>
        pad.left +
        (points.length === 1 ? iw / 2 : (i / (points.length - 1)) * iw);
    const y = (v: number) =>
        pad.top + ih - ((v - yMin) / (yMax - yMin || 1)) * ih;
    const path = points
        .map((p, i) => `${i ? 'L' : 'M'}${x(i)},${y(p.value)}`)
        .join('');
    const last = points.length - 1;
    const labelEvery = Math.max(
        1,
        Math.ceil(points.length / Math.max(2, Math.floor(iw / 72))),
    );

    const onMove = (e: React.PointerEvent<SVGRectElement>) => {
        const box = e.currentTarget.getBoundingClientRect();
        const px = e.clientX - box.left;
        const i = Math.round((px / box.width) * (points.length - 1));
        setHover(Math.min(last, Math.max(0, i)));
    };

    const h = hover !== null ? points[hover] : null;

    return (
        <figure
            data-slot="trend-chart"
            className={cn('flex flex-col gap-2', className)}
            {...props}
        >
            {!hideCaption ? (
                <figcaption className="text-[11px] leading-4 tracking-[0.05em] text-muted-foreground uppercase">
                    {title}
                </figcaption>
            ) : null}
            <div ref={ref} className="relative w-full">
                <svg
                    width={width}
                    height={height}
                    role="img"
                    aria-label={`${title}: from ${fmt(points[0].value)} to ${fmt(points[last].value)} ${unit}`}
                    className="block overflow-visible"
                >
                    {ticks.map((t) => (
                        <g key={t}>
                            <line
                                x1={pad.left}
                                x2={width - pad.right}
                                y1={y(t)}
                                y2={y(t)}
                                className="stroke-border"
                                strokeWidth={1}
                            />
                            <text
                                x={pad.left - 8}
                                y={y(t)}
                                dy="0.32em"
                                textAnchor="end"
                                className="fill-muted-foreground text-[11px] tabular-nums"
                            >
                                {fmt(t)}
                            </text>
                        </g>
                    ))}
                    {points.map((p, i) =>
                        i % labelEvery === 0 || i === last ? (
                            <text
                                key={i}
                                x={x(i)}
                                y={height - 6}
                                textAnchor={
                                    i === last
                                        ? 'end'
                                        : i === 0
                                          ? 'start'
                                          : 'middle'
                                }
                                className="fill-muted-foreground text-[11px]"
                            >
                                {p.label}
                            </text>
                        ) : null,
                    )}
                    {h ? (
                        <line
                            x1={x(hover!)}
                            x2={x(hover!)}
                            y1={pad.top}
                            y2={pad.top + ih}
                            className="stroke-muted-foreground"
                            strokeWidth={1}
                        />
                    ) : null}
                    <path
                        d={path}
                        fill="none"
                        className="stroke-primary"
                        strokeWidth={2}
                        strokeLinejoin="round"
                        strokeLinecap="round"
                    />
                    {points.map((p, i) =>
                        p.pr || i === last || i === hover ? (
                            <circle
                                key={i}
                                cx={x(i)}
                                cy={y(p.value)}
                                r={p.pr ? 5 : 4}
                                className={cn(
                                    'stroke-card',
                                    p.pr ? 'fill-primary' : 'fill-foreground',
                                )}
                                strokeWidth={2}
                            />
                        ) : null,
                    )}
                    <text
                        x={x(last) + 10}
                        y={y(points[last].value)}
                        dy="0.32em"
                        className="fill-foreground text-xs tabular-nums"
                    >
                        {fmt(points[last].value)}
                    </text>
                    <rect
                        x={pad.left}
                        y={pad.top}
                        width={iw}
                        height={ih}
                        fill="transparent"
                        onPointerMove={onMove}
                        onPointerDown={onMove}
                        onPointerLeave={() => setHover(null)}
                    />
                </svg>
                {h ? (
                    <div
                        role="status"
                        className="pointer-events-none absolute top-0 z-10 rounded-md border bg-popover px-2.5 py-1.5 text-xs whitespace-nowrap text-popover-foreground shadow-md"
                        style={{
                            left: Math.min(
                                Math.max(x(hover!) - 50, 0),
                                width - 110,
                            ),
                        }}
                    >
                        <div className="text-muted-foreground">{h.label}</div>
                        <div className="tabular-nums">
                            {fmt(h.value)} {unit}
                            {h.pr ? (
                                <span className="text-primary"> · PR</span>
                            ) : null}
                        </div>
                    </div>
                ) : null}
            </div>
            <table className="sr-only">
                <caption>{title}</caption>
                <tbody>
                    {points.map((p, i) => (
                        <tr key={i}>
                            <th scope="row">{p.label}</th>
                            <td>
                                {fmt(p.value)} {unit}
                                {p.pr ? ' (PR)' : ''}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </figure>
    );
}

export { TrendChart };
export type { TrendChartProps, TrendPoint };

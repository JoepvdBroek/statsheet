import {
    ArrowDownIcon,
    ArrowUpIcon,
    MoreHorizontalIcon,
    Trash2Icon,
} from 'lucide-react';
import type * as React from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type MoveRemoveItemsProps = {
    removeLabel: string;
    isFirst: boolean;
    isLast: boolean;
    onMove: (offset: -1 | 1) => void;
    onRemove: () => void;
};

/** Menu items to move an item one place up or down in its list, or remove it. */
function MoveRemoveItems({
    removeLabel,
    isFirst,
    isLast,
    onMove,
    onRemove,
}: MoveRemoveItemsProps) {
    return (
        <>
            <DropdownMenuItem disabled={isFirst} onSelect={() => onMove(-1)}>
                <ArrowUpIcon aria-hidden="true" />
                Move up
            </DropdownMenuItem>
            <DropdownMenuItem disabled={isLast} onSelect={() => onMove(1)}>
                <ArrowDownIcon aria-hidden="true" />
                Move down
            </DropdownMenuItem>
            <DropdownMenuItem variant="destructive" onSelect={onRemove}>
                <Trash2Icon aria-hidden="true" />
                {removeLabel}
            </DropdownMenuItem>
        </>
    );
}

/** A More button opening a menu to move or remove an item, e.g. an Exercise or a Set. */
function ItemMenu({
    label,
    children,
    ...items
}: MoveRemoveItemsProps & {
    label: string;
    /** Extra items shown before Move up. */
    children?: React.ReactNode;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    aria-label={label}
                    className="flex size-10 shrink-0 items-center justify-center rounded-full text-muted-foreground outline-none hover:bg-accent hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 [&>svg]:size-4"
                >
                    <MoreHorizontalIcon aria-hidden="true" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {children}
                <MoveRemoveItems {...items} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export { ItemMenu, MoveRemoveItems };

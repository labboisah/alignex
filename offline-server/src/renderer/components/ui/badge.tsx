import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const badgeVariants = cva('inline-flex items-center rounded-md border px-2.5 py-0.5 text-xs font-semibold transition-colors', {
    variants: {
        variant: {
            default: 'border-transparent bg-primary text-white',
            secondary: 'border-border bg-lightBackground text-slateDark',
            outline: 'border-border text-slateDark',
            warning: 'border-accentOrange/30 bg-accentOrange/10 text-accentOrange',
        },
    },
    defaultVariants: {
        variant: 'default',
    },
});

export interface BadgeProps extends React.HTMLAttributes<HTMLDivElement>, VariantProps<typeof badgeVariants> {}

function Badge({ className, variant, ...props }: BadgeProps) {
    return <div className={cn(badgeVariants({ variant }), className)} {...props} />;
}

export { Badge, badgeVariants };

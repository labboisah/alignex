import * as React from 'react';
import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '../../utils/cn';

const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-info focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
    {
        variants: {
            variant: {
                default: 'bg-primary text-white hover:bg-darkGreen active:bg-darkGreen',
                secondary: 'border border-border bg-white text-slateDark hover:bg-lightBackground active:bg-slate-100',
                outline: 'border border-border bg-white text-slateDark hover:border-primary/50 hover:bg-primary/5 active:bg-primary/10',
                danger: 'bg-danger text-white hover:bg-danger/90 active:bg-danger/80',
                ghost: 'text-slateDark hover:bg-lightBackground active:bg-slate-100',
            },
            size: {
                default: 'h-11 px-4 py-2',
                sm: 'h-9 px-3',
                lg: 'h-12 px-6 text-base',
                icon: 'h-11 w-11',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement>, VariantProps<typeof buttonVariants> {
    asChild?: boolean;
}

const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(({ className, variant, size, asChild = false, ...props }, ref) => {
    const Comp = asChild ? Slot : 'button';
    return <Comp ref={ref} className={cn(buttonVariants({ variant, size, className }))} {...props} />;
});

Button.displayName = 'Button';

export { Button, buttonVariants };

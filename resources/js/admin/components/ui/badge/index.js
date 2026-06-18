import { cva } from 'class-variance-authority';

export { default as Badge } from './Badge.vue';

export const badgeVariants = cva(
    'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold',
    {
        variants: {
            variant: {
                default: 'border-transparent bg-primary text-primary-foreground',
                secondary: 'border-transparent bg-secondary text-secondary-foreground',
                outline: 'text-foreground',
                success: 'border-transparent bg-teal-100 text-teal-700',
                warning: 'border-transparent bg-orange-200 text-orange-700',
                destructive: 'border-transparent bg-red-100 text-red-700',
            },
        },
        defaultVariants: { variant: 'default' },
    }
);

import { cva } from "class-variance-authority";

export { default as Badge } from "./Badge.vue";

export const badgeVariants = cva(
  "inline-flex items-center justify-center rounded-full border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-3 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden",
  {
    variants: {
      variant: {
        default:
          "border-transparent bg-primary text-primary-foreground [a&]:hover:bg-primary/90",
        secondary:
          "border-transparent bg-secondary text-secondary-foreground [a&]:hover:bg-secondary/90",
        destructive:
          "border-transparent bg-destructive text-on-accent [a&]:hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40",
        outline:
          "text-foreground [a&]:hover:bg-hover [a&]:hover:text-ink",
        // The status tones, formerly admin/StatusPill. `active` exists apart from `ok` so that
        // a project's `agreed` and `building` never share a colour.
        ok: "px-2.5 py-1 bg-success/15 text-success border-success/25",
        active: "px-2.5 py-1 bg-accent/15 text-accent border-accent/25",
        pending: "px-2.5 py-1 bg-signal/15 text-signal border-signal/25",
        off: "px-2.5 py-1 bg-danger/15 text-danger border-danger/25",
        neutral: "px-2.5 py-1 bg-raised-solid/5 text-ink-3 border-rule",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
);

import { cva } from "class-variance-authority";
import { cn } from "@/lib/utils";

export { default as Button } from "./Button.vue";

const variants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive",
  {
    variants: {
      variant: {
        default: "bg-primary text-primary-foreground hover:bg-primary/90",
        destructive:
          "bg-destructive text-on-accent hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40",
        outline:
          "border bg-background shadow-[var(--shadow-sm)] hover:bg-hover hover:text-ink dark:bg-input/30 dark:border-input dark:hover:bg-input/50",
        secondary:
          "bg-secondary text-secondary-foreground hover:bg-secondary/80",
        ghost:
          "hover:bg-hover hover:text-ink",
        link: "text-primary underline-offset-4 hover:underline",
        // Tinted outlines for a row's verdict — approve or complete, decline or cancel — where a
        // filled button per row would be a wall of colour. And the quiet delete icon.
        success: "border border-success/25 text-success hover:bg-success/10",
        danger: "border border-danger/25 text-danger hover:bg-danger/10",
        "ghost-danger": "text-ink-4 hover:bg-danger/10 hover:text-danger",
        // The conversion action — "start a draft" — in the signal colour, so it is never confused
        // with an ordinary indigo control. 8px corners, as the public CTAs have always had.
        signal: "bg-signal font-semibold text-on-signal hover:brightness-110",
        // The secondary action as `.yhn-btn-2` drew it: an accent-tinted pill.
        soft: "border-2 border-accent/55 bg-accent/10 font-bold text-accent hover:border-accent hover:bg-accent/20",
        // The primary action: the brand gradient as a pill, as `.yhn-btn-1` drew it. Hover is
        // colour only — design-system.md calls lift and zoom transforms debt.
        brand:
          "bg-linear-135 from-(--brand-a) to-(--brand-b) font-bold text-on-accent shadow-[var(--glow)] hover:brightness-110 disabled:opacity-70",
      },
      size: {
        default: "h-9 px-4 py-2 has-[>svg]:px-3",
        sm: "h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5",
        lg: "h-10 rounded-md px-6 has-[>svg]:px-4",
        cta: "h-11 rounded-md px-6 has-[>svg]:px-5",
        xl: "h-14 rounded-md gap-3 px-8 text-lg has-[>svg]:px-7",
        icon: "size-9",
        "icon-sm": "size-8",
        "icon-lg": "size-10",
      },
    },
    // After the sizes, so their `rounded-md` cannot take the pill back off.
    compoundVariants: [{ variant: ["brand", "soft"], class: "rounded-full" }],
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
);

// Resolved through `cn`, so the class list is conflict-free wherever it lands. `Button` merges
// its own, but a `<Link>` or `<a>` styled with `buttonVariants()` receives the string as is —
// and with both `rounded-md` (the base) and `rounded-full` (the pill) in it, stylesheet order
// picked the square corners on every pill-shaped link.
export const buttonVariants = (props) => cn(variants(props));

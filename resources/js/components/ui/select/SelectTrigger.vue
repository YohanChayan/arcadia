<script setup>
// The house field look (formerly `.yhn-field-1`), so a select sits level with the inputs.
import { ChevronDown } from "@lucide/vue";
import { reactiveOmit } from "@vueuse/core";
import { SelectIcon, SelectTrigger, useForwardProps } from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps({
  disabled: { type: Boolean, required: false },
  reference: { type: null, required: false },
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false },
  class: { type: null, required: false },
  size: { type: String, required: false, default: "default" },
});

const delegatedProps = reactiveOmit(props, "class", "size");
const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
  <SelectTrigger
    data-slot="select-trigger"
    :data-size="size"
    v-bind="forwardedProps"
    :class="
      cn(
        `flex w-full items-center justify-between gap-2 px-3 text-left whitespace-nowrap data-[size=default]:py-3 data-[size=default]:text-base data-[size=sm]:py-2 data-[size=sm]:text-sm data-[placeholder]:text-ink-4 data-[state=open]:border-accent [&_svg:not([class*='text-'])]:text-ink-3 *:data-[slot=select-value]:line-clamp-1 *:data-[slot=select-value]:flex *:data-[slot=select-value]:items-center *:data-[slot=select-value]:gap-2 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4`,
        'rounded-panel border-2 border-rule bg-well text-ink backdrop-blur-md transition-colors duration-200 outline-none placeholder:text-ink-4 focus-visible:border-accent focus-visible:bg-ink/5 focus-visible:shadow-[0_0_0_4px_color-mix(in_srgb,var(--accent)_18%,transparent)] aria-invalid:border-danger aria-invalid:bg-danger/5 disabled:cursor-not-allowed disabled:opacity-50',
        props.class,
      )
    "
  >
    <slot />
    <SelectIcon as-child>
      <ChevronDown class="size-4 opacity-50" />
    </SelectIcon>
  </SelectTrigger>
</template>

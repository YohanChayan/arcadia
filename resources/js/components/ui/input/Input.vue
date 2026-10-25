<script setup>
// The house field look (formerly `.yhn-field-1`), kept by decision rather than shadcn's.
import { useVModel } from "@vueuse/core";
import { cn } from "@/lib/utils";

const props = defineProps({
  defaultValue: { type: [String, Number], required: false },
  modelValue: { type: [String, Number], required: false },
  class: { type: null, required: false },
});

const emits = defineEmits(["update:modelValue"]);

const modelValue = useVModel(props, "modelValue", emits, {
  passive: true,
  defaultValue: props.defaultValue,
});
</script>

<template>
  <input
    v-model="modelValue"
    data-slot="input"
    :class="
      cn(
        'w-full min-w-0 px-3 py-3 text-base selection:bg-primary selection:text-primary-foreground file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-ink',
        'rounded-panel border-2 border-rule bg-well text-ink backdrop-blur-md transition-colors duration-200 outline-none placeholder:text-ink-4 focus-visible:border-accent focus-visible:bg-ink/5 focus-visible:shadow-[0_0_0_4px_color-mix(in_srgb,var(--accent)_18%,transparent)] aria-invalid:border-danger aria-invalid:bg-danger/5 disabled:cursor-not-allowed disabled:opacity-50',
        props.class,
      )
    "
  />
</template>

<script setup lang="ts">
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxViewport,
} from 'reka-ui';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { AdministratorOption } from '@/types';

const props = defineProps<{
    administrators: AdministratorOption[];
    id?: string;
}>();

const selected = defineModel<string[]>({ default: () => [] });
const searchTerm = ref('');

const selectedOptions = computed(() =>
    props.administrators.filter((administrator) =>
        selected.value.includes(administrator.id),
    ),
);

const filtered = computed(() => {
    const term = searchTerm.value.trim().toLowerCase();

    return props.administrators.filter(
        (administrator) =>
            term === '' ||
            administrator.name.toLowerCase().includes(term) ||
            administrator.email.toLowerCase().includes(term),
    );
});

const remove = (id: string) => {
    selected.value = selected.value.filter((value) => value !== id);
};
</script>

<template>
    <div>
        <input
            v-for="id in selected"
            :key="id"
            type="hidden"
            name="administrators[]"
            :value="id"
        />

        <ComboboxRoot
            v-model="selected"
            v-model:search-term="searchTerm"
            multiple
            ignore-filter
            class="relative"
        >
            <ComboboxAnchor
                class="flex min-h-9 flex-wrap items-center gap-1 rounded-md border border-input bg-transparent px-2 py-1 shadow-xs focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 dark:bg-input/30"
            >
                <Badge
                    v-for="administrator in selectedOptions"
                    :key="administrator.id"
                    variant="secondary"
                >
                    {{ administrator.name }}
                    <button
                        type="button"
                        class="rounded-full outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        :aria-label="`Remove ${administrator.name}`"
                        @click="remove(administrator.id)"
                    >
                        <span aria-hidden="true">&times;</span>
                    </button>
                </Badge>
                <ComboboxInput
                    :id="id"
                    placeholder="Search administrators..."
                    class="min-w-32 flex-1 bg-transparent py-0.5 text-sm outline-none placeholder:text-muted-foreground"
                />
            </ComboboxAnchor>

            <ComboboxPortal>
                <ComboboxContent
                    position="popper"
                    :side-offset="4"
                    class="z-50 w-(--reka-combobox-trigger-width) rounded-md border bg-popover p-1 text-popover-foreground shadow-md"
                >
                    <ComboboxViewport class="max-h-60 overflow-y-auto">
                        <ComboboxEmpty
                            class="px-2 py-1.5 text-sm text-muted-foreground"
                        >
                            No administrators found.
                        </ComboboxEmpty>
                        <ComboboxItem
                            v-for="administrator in filtered"
                            :key="administrator.id"
                            :value="administrator.id"
                            class="flex cursor-default flex-col rounded-sm px-2 py-1.5 text-sm outline-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground data-[state=checked]:font-medium"
                        >
                            <span>{{ administrator.name }}</span>
                            <span class="text-xs text-muted-foreground">
                                {{ administrator.email }}
                            </span>
                        </ComboboxItem>
                    </ComboboxViewport>
                </ComboboxContent>
            </ComboboxPortal>
        </ComboboxRoot>
    </div>
</template>

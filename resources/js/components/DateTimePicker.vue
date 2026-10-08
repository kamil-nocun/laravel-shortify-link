<script setup lang="ts">
import { parseDate } from '@internationalized/date';
import type { DateValue } from '@internationalized/date';
import { CalendarIcon, ChevronLeft, ChevronRight } from '@lucide/vue';
import {
    CalendarCell,
    CalendarCellTrigger,
    CalendarGrid,
    CalendarGridBody,
    CalendarGridHead,
    CalendarGridRow,
    CalendarHeadCell,
    CalendarHeader,
    CalendarHeading,
    CalendarNext,
    CalendarPrev,
    CalendarRoot,
} from 'reka-ui';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

/** Value is a local `YYYY-MM-DDTHH:mm` string, or '' when empty. */
const model = defineModel<string>({ default: '' });

defineProps<{ id?: string }>();

const date = computed<DateValue | undefined>({
    get: () => (model.value ? parseDate(model.value.slice(0, 10)) : undefined),
    set: (value) => {
        model.value = value ? `${value.toString()}T${time.value}` : '';
    },
});

const time = computed({
    get: () => model.value.slice(11, 16) || '12:00',
    set: (value: string) => {
        if (model.value) {
            model.value = `${model.value.slice(0, 10)}T${value || '00:00'}`;
        }
    },
});

const label = computed(() =>
    model.value ? model.value.replace('T', ' ') : 'Pick date and time',
);
</script>

<template>
    <Popover>
        <PopoverTrigger as-child>
            <Button
                :id="id"
                type="button"
                variant="outline"
                class="w-full justify-start font-normal"
            >
                <CalendarIcon class="size-4" />
                {{ label }}
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-auto space-y-3 p-3" align="start">
            <CalendarRoot
                v-slot="{ weekDays, grid }"
                v-model="date"
                :week-starts-on="1"
            >
                <CalendarHeader class="flex items-center justify-between">
                    <CalendarPrev
                        class="rounded p-1 hover:bg-accent"
                        aria-label="Previous month"
                    >
                        <ChevronLeft class="size-4" />
                    </CalendarPrev>
                    <CalendarHeading class="text-sm font-medium" />
                    <CalendarNext
                        class="rounded p-1 hover:bg-accent"
                        aria-label="Next month"
                    >
                        <ChevronRight class="size-4" />
                    </CalendarNext>
                </CalendarHeader>
                <CalendarGrid
                    v-for="month in grid"
                    :key="month.value.toString()"
                    class="mt-2"
                >
                    <CalendarGridHead>
                        <CalendarGridRow class="grid grid-cols-7">
                            <CalendarHeadCell
                                v-for="day in weekDays"
                                :key="day"
                                class="text-center text-xs text-muted-foreground"
                            >
                                {{ day }}
                            </CalendarHeadCell>
                        </CalendarGridRow>
                    </CalendarGridHead>
                    <CalendarGridBody>
                        <CalendarGridRow
                            v-for="(week, index) in month.rows"
                            :key="index"
                            class="grid grid-cols-7"
                        >
                            <CalendarCell
                                v-for="day in week"
                                :key="day.toString()"
                                :date="day"
                                class="p-0.5 text-center"
                            >
                                <CalendarCellTrigger
                                    :day="day"
                                    :month="month.value"
                                    class="size-8 rounded-md text-sm hover:bg-accent data-[outside-view]:text-muted-foreground/50 data-[selected]:bg-primary data-[selected]:text-primary-foreground data-[today]:font-bold"
                                />
                            </CalendarCell>
                        </CalendarGridRow>
                    </CalendarGridBody>
                </CalendarGrid>
            </CalendarRoot>
            <div class="flex items-center gap-2">
                <Input v-model="time" type="time" aria-label="Time" />
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="model = ''"
                >
                    Clear
                </Button>
            </div>
        </PopoverContent>
    </Popover>
</template>

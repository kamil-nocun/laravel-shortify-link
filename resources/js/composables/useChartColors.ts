import { onBeforeUnmount, onMounted, ref } from 'vue';
import type { Ref } from 'vue';

export type ChartColors = {
    primary: string;
    muted: string;
    border: string;
    palette: string[];
};

const fallback: ChartColors = {
    primary: '#888',
    muted: '#888',
    border: '#8884',
    palette: ['#888', '#aaa', '#666', '#ccc', '#444'],
};

/**
 * Theme colors for Chart.js, re-read whenever the light/dark class changes.
 */
export function useChartColors(): Ref<ChartColors> {
    const colors = ref<ChartColors>(fallback);
    let observer: MutationObserver | null = null;

    function read(): void {
        const style = getComputedStyle(document.documentElement);
        const value = (name: string, otherwise: string) =>
            style.getPropertyValue(name).trim() || otherwise;

        colors.value = {
            primary: value('--primary', fallback.primary),
            muted: value('--muted-foreground', fallback.muted),
            border: value('--border', fallback.border),
            palette: [1, 2, 3, 4, 5].map((index) =>
                value(`--chart-${index}`, fallback.palette[index - 1]),
            ),
        };
    }

    onMounted(() => {
        read();
        observer = new MutationObserver(read);
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class'],
        });
    });

    onBeforeUnmount(() => observer?.disconnect());

    return colors;
}

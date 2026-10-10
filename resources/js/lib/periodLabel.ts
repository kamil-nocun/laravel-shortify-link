export function periodLabel(period: string): string {
    const days = /^(\d+)d$/.exec(period);

    if (days) {
        return `last ${days[1]} days`;
    }

    return period === 'all' ? 'all time' : 'selected period';
}

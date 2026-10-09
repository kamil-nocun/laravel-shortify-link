export type Link = {
    id: string;
    title: string | null;
    original_url: string;
    code: string;
    short_url: string;
    is_active: boolean;
    expires_at: string | null;
    clicks_count?: number;
};

export type StatsRow = {
    name: string;
    count: number;
};

export type DailyClicks = {
    date: string;
    count: number;
};

export type StatsPeriodParams = {
    period: string;
    date: string | null;
    month: string | null;
    year: string | null;
    from: string | null;
    to: string | null;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type StatsFilterKey =
    'country' | 'city' | 'browser' | 'os' | 'device_type';

export type StatsFilters = Record<StatsFilterKey, string | null>;

export type StatsOptions = {
    countries: string[];
    cities: string[];
    browsers: string[];
    systems: string[];
    devices: string[];
};

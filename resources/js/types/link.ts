export type Link = {
    id: string;
    title: string | null;
    original_url: string;
    code: string;
    short_url: string;
    is_active: boolean;
    expires_at: string | null;
    disabled_by_admin_at: string | null;
    clicks_count?: number;
};

export type StatsRow = {
    name: string;
    count: number;
};

export type StatsTable = {
    rows: StatsRow[];
    page: number;
    lastPage: number;
    total: number;
    perPage: number;
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
    | 'country'
    | 'city'
    | 'browser'
    | 'os'
    | 'device_type';

export type StatsFilters = Record<StatsFilterKey, string | null>;

export type StatsOptions = {
    countries: string[];
    cities: string[];
    browsers: string[];
    systems: string[];
    devices: string[];
};

export type StatsTableKey =
    | 'countries'
    | 'cities'
    | 'browsers'
    | 'systems'
    | 'devices';

export type StatsSorts = Record<StatsTableKey, string>;

export type AdminCounters = {
    users?: number;
    links: number;
    activeLinks: number;
    disabledLinks: number;
    clicks: number;
};

export type TopLink = {
    id: string;
    title: string | null;
    code: string;
    clicks: number;
};

export type TopUser = {
    id: string;
    name: string;
    email: string;
    clicks: number;
};

export type AdminUser = {
    id: string;
    name: string;
    email: string;
    role: 'user' | 'admin' | 'super_admin';
    blocked_at: string | null;
    created_at: string;
    links_count: number;
    can: {
        block: boolean;
        delete: boolean;
        changeRole: boolean;
    };
};

export type Link = {
    id: string;
    title: string | null;
    original_url: string;
    code: string;
    short_url: string;
    is_active: boolean;
    expires_at: string | null;
    disabled_by_admin_at: string | null;
    disabled_by?: string | null;
    disabled_by_name?: string | null;
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
    role: string | null;
    role_id: string | null;
    blocked_at: string | null;
    blocked_by_id: string | null;
    blocked_by_name: string | null;
    created_at: string;
    links_count: number;
    can: {
        block: boolean;
        manageDomains: boolean;
        createDomain: boolean;
        disableLinks: boolean;
        disableDomains: boolean;
        update: boolean;
        createLink: boolean;
        delete: boolean;
        changeRole: boolean;
        assignAdministrators: boolean;
    };
};

export type AdministratorOption = {
    id: string;
    name: string;
    email: string;
};

export type AdminDomainRow = {
    id: string;
    host: string;
    status: 'pending' | 'active' | 'disabled';
    disabled_by_name: string | null;
    links_count: number;
};

export type DashboardPackage = {
    package_name: string | null;
    link_limit: number | null;
    links_used: number;
    domain_limit: number | null;
    domains_used: number;
    billing_period: string | null;
    expires_at: string | null;
};

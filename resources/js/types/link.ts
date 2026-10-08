export type Link = {
    id: string;
    title: string | null;
    original_url: string;
    code: string;
    short_url: string;
    is_active: boolean;
    expires_at: string | null;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type PageStatus = 'draft' | 'published';

export type PageListItem = {
    id: string;
    slug: string;
    status: PageStatus;
    title: string | null;
    updated_at: string;
};

export type PageContent = {
    id: string;
    slug: string;
    status: PageStatus;
    title: string | null;
    body: string | null;
};

export type PageVersionListItem = {
    id: string;
    title: string;
    created_at: string;
    author_name: string | null;
    is_current: boolean;
};

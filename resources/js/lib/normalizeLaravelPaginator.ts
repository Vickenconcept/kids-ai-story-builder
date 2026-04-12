/**
 * Laravel paginators serialize with pagination fields at the root (current_page, last_page, …).
 * Some stacks nest them under `meta`. Normalize to `{ data, links, meta }` for Vue.
 */
export type PaginationLink = {
    url: string | null;
    label: string;
    active?: boolean;
    page?: number | null;
};

export type NormalizedPagination<T> = {
    data: T[];
    links: PaginationLink[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
};

function emptyPage<T>(): NormalizedPagination<T> {
    return {
        data: [],
        links: [],
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 15,
            total: 0,
            from: null,
            to: null,
        },
    };
}

export function normalizeLaravelPaginator<T>(raw: unknown): NormalizedPagination<T> {
    if (raw == null) {
        return emptyPage();
    }

    if (Array.isArray(raw)) {
        const len = raw.length;

        return {
            data: raw as T[],
            links: [],
            meta: {
                current_page: 1,
                last_page: 1,
                per_page: Math.max(len, 1),
                total: len,
                from: len ? 1 : null,
                to: len ? len : null,
            },
        };
    }

    if (typeof raw !== 'object') {
        return emptyPage();
    }

    const o = raw as Record<string, unknown>;
    const data = Array.isArray(o.data) ? (o.data as T[]) : [];

    if (o.meta && typeof o.meta === 'object') {
        const m = o.meta as Record<string, unknown>;

        return {
            data,
            links: Array.isArray(o.links) ? (o.links as PaginationLink[]) : [],
            meta: {
                current_page: Number(m.current_page ?? 1),
                last_page: Number(m.last_page ?? 1),
                per_page: Number(m.per_page ?? 15),
                total: Number(m.total ?? data.length),
                from: (m.from as number | null | undefined) ?? null,
                to: (m.to as number | null | undefined) ?? null,
            },
        };
    }

    return {
        data,
        links: Array.isArray(o.links) ? (o.links as PaginationLink[]) : [],
        meta: {
            current_page: Number(o.current_page ?? 1),
            last_page: Number(o.last_page ?? 1),
            per_page: Number(o.per_page ?? 15),
            total: Number(o.total ?? data.length),
            from: (o.from as number | null | undefined) ?? null,
            to: (o.to as number | null | undefined) ?? null,
        },
    };
}

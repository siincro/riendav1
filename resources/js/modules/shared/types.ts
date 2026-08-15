/**
 * Tipos compartidos por todos los módulos del frontend de RIENDA.
 */

/** Una opción de un `select`, tal como la exponen los enums del backend. */
export type Option = {
    value: string;
    label: string;
};

/** Un enlace de la paginación de Laravel. */
export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

/**
 * Colección paginada tal como la serializa un `JsonResource::collection`
 * sobre un paginador.
 */
export type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    meta: {
        current_page: number;
        from: number | null;
        to: number | null;
        last_page: number;
        per_page: number;
        total: number;
    };
};

/** Estado que comparten participantes y caballos. */
export type ActiveStatus = 'active' | 'inactive';

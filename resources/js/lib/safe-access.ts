/**
 * Safe access utility for nested objects
 * Prevents "Cannot read properties of undefined" errors
 */

export interface PaginatedData<T> {
  data: T[];
  links: unknown[];
  meta: {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
  };
}

/**
 * Create default paginated data structure
 */
export function createDefaultPaginatedData<T>(): PaginatedData<T> {
  return {
    data: [],
    links: [],
    meta: {
      current_page: 1,
      from: null,
      last_page: 1,
      per_page: 15,
      to: null,
      total: 0,
    },
  };
}

/**
 * Safely access nested object properties
 */
export function safeGet<T, K extends keyof T>(
  obj: T | null | undefined,
  key: K,
  defaultValue: T[K]
): T[K] {
  if (obj === null || obj === undefined) {
    return defaultValue;
  }
  return obj[key] ?? defaultValue;
}

/**
 * Safely access deeply nested properties
 */
export function safeDeepGet<T>(
  obj: unknown,
  path: string,
  defaultValue: T
): T {
  const keys = path.split('.');
  let current: unknown = obj;

  for (const key of keys) {
    if (current === null || current === undefined || typeof current !== 'object') {
      return defaultValue;
    }
    current = (current as Record<string, unknown>)[key];
  }

  return (current ?? defaultValue) as T;
}

/**
 * Ensure paginated data has proper structure
 */
export function ensurePaginatedData<T>(
  data: PaginatedData<T> | null | undefined
): PaginatedData<T> {
  if (!data) {
    return createDefaultPaginatedData<T>();
  }

  return {
    data: data.data ?? [],
    links: data.links ?? [],
    meta: {
      current_page: data.meta?.current_page ?? 1,
      from: data.meta?.from ?? null,
      last_page: data.meta?.last_page ?? 1,
      per_page: data.meta?.per_page ?? 15,
      to: data.meta?.to ?? null,
      total: data.meta?.total ?? 0,
    },
  };
}

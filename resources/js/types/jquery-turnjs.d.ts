declare global {
    interface Window {
        jQuery: import('jquery').JQueryStatic;
        $: import('jquery').JQueryStatic;
    }

    interface JQuery {
        turn(options?: Record<string, unknown>): this;
        turn(method: string, ...args: unknown[]): this;
    }
}

export {};

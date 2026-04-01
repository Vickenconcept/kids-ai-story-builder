import type { JQueryStatic } from 'jquery';

declare global {
    interface Window {
        jQuery: JQueryStatic;
        $: JQueryStatic;
    }

    interface JQuery {
        turn(options?: Record<string, unknown>): this;
        turn(method: string, ...args: unknown[]): this;
    }
}

export {};

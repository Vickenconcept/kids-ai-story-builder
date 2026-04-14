import { reactive } from 'vue';

type ToastType = 'success' | 'error' | 'info';

type Toast = {
    id: number;
    message: string;
    type: ToastType;
    timeout: number;
};

const toasts = reactive<Toast[]>([]);
let nextToastId = 1;

const removeToast = (id: number) => {
    const index = toasts.findIndex((toast) => toast.id === id);
    if (index >= 0) {
        toasts.splice(index, 1);
    }
};

const pushToast = (message: string, type: ToastType, timeout = 4500) => {
    const id = nextToastId++;
    toasts.push({ id, message, type, timeout });
    window.setTimeout(() => removeToast(id), timeout);
};

export const useToast = () => ({
    toasts,
    removeToast,
    success: (message: string, timeout?: number) => pushToast(message, 'success', timeout),
    error: (message: string, timeout?: number) => pushToast(message, 'error', timeout),
    info: (message: string, timeout?: number) => pushToast(message, 'info', timeout),
});

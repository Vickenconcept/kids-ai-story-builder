import { ref } from 'vue';

const isOpen = ref(false);
const selectedPackId = ref<number | null>(null);

export function useCreditsModal() {
    const open = (packId?: number) => {
        selectedPackId.value = packId ?? null;
        isOpen.value = true;
    };

    const close = () => {
        isOpen.value = false;
        selectedPackId.value = null;
    };

    return {
        isOpen,
        selectedPackId,
        open,
        close,
    };
}

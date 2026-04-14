<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useToast } from '@/composables/useToast';
import { useCreditsModal } from '@/composables/useCreditsModal';

type CreditPack = {
    id: number;
    name: string;
    description: string | null;
    credits: number;
    price_cents: number;
    currency: string;
};

const page = usePage<any>();
const modal = useCreditsModal();
const toast = useToast();

const packs = computed<CreditPack[]>(() => page.props.billing?.creditPacks ?? []);
const paypalClientId = computed<string>(() => page.props.billing?.paypalClientId ?? '');

const selectedPackId = ref<number | null>(null);
const loading = ref(false);
const errorMessage = ref<string | null>(null);
const successMessage = ref<string | null>(null);

const selectedPack = computed(() =>
    packs.value.find((pack) => pack.id === selectedPackId.value) ?? null,
);

const formatPrice = (amountCents: number, currency: string) => {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: currency.toUpperCase(),
    }).format(amountCents / 100);
};

const csrfToken = () =>
    document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content') ?? '';

const postJson = async (url: string, payload: Record<string, unknown>) => {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const firstValidationError = Object.values(data?.errors ?? {})?.[0];
        const validationMessage = Array.isArray(firstValidationError)
            ? String(firstValidationError[0] ?? '')
            : null;

        throw new Error(validationMessage || data?.message || 'Payment request failed.');
    }

    return data;
};

const ensurePayPalScript = async () => {
    const win = window as Window & { paypal?: any };
    if (win.paypal?.Buttons) {
        return;
    }

    if (!paypalClientId.value) {
        throw new Error('PayPal client id is not configured.');
    }

    await new Promise<void>((resolve, reject) => {
        const existing = document.getElementById('paypal-sdk-script');
        if (existing) {
            existing.addEventListener('load', () => resolve(), { once: true });
            existing.addEventListener('error', () => reject(new Error('Unable to load PayPal SDK.')), { once: true });
            return;
        }

        const script = document.createElement('script');
        script.id = 'paypal-sdk-script';
        script.src = `https://www.paypal.com/sdk/js?client-id=${encodeURIComponent(paypalClientId.value)}&currency=USD&intent=capture`;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Unable to load PayPal SDK.'));
        document.head.appendChild(script);
    });
};

const renderPayPalButtons = async () => {
    const target = document.getElementById('paypal-buttons-container');
    if (!target || !selectedPack.value) {
        return;
    }

    loading.value = true;
    errorMessage.value = null;
    successMessage.value = null;

    try {
        await ensurePayPalScript();

        const win = window as Window & { paypal?: any };
        if (!win.paypal?.Buttons) {
            throw new Error('PayPal SDK is not available.');
        }

        target.innerHTML = '';

        win.paypal
            .Buttons({
                style: {
                    shape: 'pill',
                    label: 'pay',
                    height: 44,
                },
                createOrder: async () => {
                    const data = await postJson('/credits/paypal/order', {
                        pack_id: selectedPack.value?.id,
                    });

                    return data.id;
                },
                onApprove: async (data: { orderID: string }) => {
                    const payload = await postJson('/credits/paypal/capture', {
                        pack_id: selectedPack.value?.id,
                        order_id: data.orderID,
                    });

                    const message = String(payload.message ?? 'Payment completed.');
                    successMessage.value = message;
                    toast.success(message);
                    router.reload();
                },
                onError: (error: unknown) => {
                    const message =
                        error instanceof Error
                            ? error.message
                            : 'PayPal checkout failed. Please try again.';
                    errorMessage.value = message;
                    toast.error(message);
                },
                onCancel: () => {
                    const message = 'Payment was canceled.';
                    errorMessage.value = message;
                    toast.info(message);
                },
            })
            .render(target);
    } catch (error) {
        const message =
            error instanceof Error
                ? error.message
                : 'Could not initialize payment checkout.';
        errorMessage.value = message;
        toast.error(message);
    } finally {
        loading.value = false;
    }
};

watch(
    () => modal.isOpen.value,
    (open) => {
        if (open) {
            selectedPackId.value =
                modal.selectedPackId.value ?? packs.value[0]?.id ?? null;
        } else {
            errorMessage.value = null;
            successMessage.value = null;
        }
    },
);

watch([() => modal.isOpen.value, selectedPackId, packs], async ([open]) => {
    if (!open) {
        return;
    }

    await nextTick();
    await renderPayPalButtons();
}, { flush: 'post' });
</script>

<template>
    <Dialog :open="modal.isOpen.value" @update:open="(open) => (!open ? modal.close() : undefined)">
        <DialogContent class="max-h-[85vh] sm:max-w-xl overflow-hidden">
            <DialogHeader>
                <DialogTitle>Buy More Credits</DialogTitle>
                <DialogDescription>
                    Pick a one-time credit pack and continue creating stories without interruption.
                </DialogDescription>
            </DialogHeader>

            <div class="max-h-[70vh] space-y-4 overflow-y-auto pr-1">
                <div class="grid gap-2 sm:grid-cols-3">
                    <button
                        v-for="pack in packs"
                        :key="pack.id"
                        type="button"
                        class="rounded-lg border px-3 py-3 text-left transition"
                        :class="pack.id === selectedPackId ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/50'"
                        @click="selectedPackId = pack.id"
                    >
                        <p class="text-sm font-semibold">{{ pack.name }}</p>
                        <p class="text-xs text-muted-foreground">{{ pack.credits }} credits</p>
                        <p class="mt-2 text-sm font-medium">{{ formatPrice(pack.price_cents, pack.currency) }}</p>
                    </button>
                </div>

                <div v-if="selectedPack" class="rounded-lg border border-border/70 bg-muted/30 p-3 text-sm">
                    <p class="font-medium">{{ selectedPack.name }}</p>
                    <p class="mt-1 text-muted-foreground">{{ selectedPack.description || 'One-time credit top-up.' }}</p>
                </div>

                <p v-if="errorMessage" class="text-sm text-destructive">{{ errorMessage }}</p>
                <p v-if="successMessage" class="text-sm text-emerald-600">{{ successMessage }}</p>

                <div id="paypal-buttons-container" class="min-h-11" />

                <div class="flex justify-end">
                    <Button type="button" variant="outline" :disabled="loading" @click="modal.close()">
                        Close
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

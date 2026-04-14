<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Plan = {
    id: number;
    name: string;
    description: string | null;
    tier: 'basic' | 'pro' | 'elite';
    included_credits: number;
    price_cents: number;
    currency: string;
    is_featured: boolean;
    feature_list: string[];
};

type PlanPurchase = {
    id: number;
    plan_name: string;
    tier: string;
    credits_floor: number;
    amount_cents: number;
    currency: string;
    status: string;
    purchased_at: string | null;
};

const props = defineProps<{
    plans: Plan[];
    currentTier: 'basic' | 'pro' | 'elite';
    storyCredits: number;
    purchases: PlanPurchase[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Plans',
        href: '/plans',
    },
];

const page = usePage<any>();
const toast = useToast();

const selectedPlanId = ref<number | null>(null);
const loading = ref(false);
const errorMessage = ref<string | null>(null);
const successMessage = ref<string | null>(null);

const paypalClientId = computed<string>(() => page.props.billing?.paypalClientId ?? '');

const rank = (tier: string) => ({ basic: 1, pro: 2, elite: 3 }[tier] ?? 0);

const upgradePlans = computed(() =>
    props.plans.filter((plan) => rank(plan.tier) > rank(props.currentTier)),
);

const selectedPlan = computed(() =>
    upgradePlans.value.find((plan) => plan.id === selectedPlanId.value) ?? upgradePlans.value[0] ?? null,
);

const formatPrice = (amountCents: number, currency: string) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: currency.toUpperCase(),
    }).format(amountCents / 100);

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
        const firstValidationError = Object.values((data as any)?.errors ?? {})?.[0];
        const validationMessage = Array.isArray(firstValidationError)
            ? String(firstValidationError[0] ?? '')
            : null;

        throw new Error(validationMessage || (data as any)?.message || 'Payment request failed.');
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

const startUpgrade = async () => {
    if (!selectedPlan.value) {
        return;
    }

    const target = document.getElementById('plan-paypal-buttons-container');
    if (!target) {
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
                    const data = await postJson('/plans/paypal/order', {
                        plan_id: selectedPlan.value?.id,
                    });

                    return (data as any).id;
                },
                onApprove: async (data: { orderID: string }) => {
                    const payload = await postJson('/plans/paypal/capture', {
                        plan_id: selectedPlan.value?.id,
                        order_id: data.orderID,
                    });

                    const message = String((payload as any).message ?? 'Plan upgraded successfully.');
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

if (!selectedPlanId.value && upgradePlans.value.length > 0) {
    selectedPlanId.value = upgradePlans.value[0].id;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Plans" />

        <div class="min-h-full bg-muted/30 dark:bg-muted/10">
            <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
                <section class="rounded-2xl border border-sidebar-border/70 bg-card p-5 shadow-sm dark:border-sidebar-border">
                    <h1 class="text-2xl font-bold tracking-tight">Upgrade Your Plan</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Current plan: <span class="font-semibold capitalize text-foreground">{{ currentTier }}</span>
                        · Current credits: <span class="font-semibold text-foreground">{{ storyCredits }}</span>
                    </p>
                    <p class="mt-2 text-sm text-muted-foreground">
                        On upgrade, your current credits are preserved and lifted to the new plan minimum if needed.
                    </p>
                </section>

                <section class="rounded-2xl border border-sidebar-border/70 bg-card p-5 shadow-sm dark:border-sidebar-border">
                    <div v-if="upgradePlans.length === 0" class="space-y-3 py-6 text-center">
                        <p class="text-sm text-muted-foreground">You are already on the highest available plan.</p>
                        <Link href="/credits" class="text-sm font-semibold text-violet-600 hover:underline">Need more capacity? Buy credits</Link>
                    </div>

                    <div v-else class="space-y-5">
                        <div class="grid gap-4 md:grid-cols-2">
                            <article
                                v-for="plan in upgradePlans"
                                :key="plan.id"
                                class="rounded-2xl border-2 p-5 transition"
                                :class="plan.id === selectedPlan?.id
                                    ? 'border-violet-500 bg-violet-50/60 dark:border-violet-700 dark:bg-violet-950/20'
                                    : 'border-border/70 bg-card hover:border-violet-300'"
                                @click="selectedPlanId = plan.id"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="text-lg font-bold">{{ plan.name }}</h3>
                                    <span class="rounded-full bg-muted px-2 py-0.5 text-xs font-semibold uppercase tracking-wide">{{ plan.tier }}</span>
                                </div>
                                <p class="mt-1 text-sm text-muted-foreground">{{ plan.description || 'Upgrade plan' }}</p>

                                <div class="mt-4 flex items-end gap-2">
                                    <span class="text-3xl font-bold">{{ formatPrice(plan.price_cents, plan.currency) }}</span>
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground">Includes at least {{ plan.included_credits }} credits</p>

                                <ul class="mt-4 space-y-1.5 text-sm">
                                    <li v-for="feature in plan.feature_list" :key="feature" class="text-muted-foreground">• {{ feature }}</li>
                                </ul>
                            </article>
                        </div>

                        <div class="rounded-xl border border-border/70 bg-muted/20 p-4">
                            <p class="text-sm font-medium">Selected: {{ selectedPlan?.name || 'None' }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">Click pay to complete the upgrade via PayPal.</p>

                            <div class="mt-4">
                                <Button type="button" :disabled="loading || !selectedPlan" @click="startUpgrade">
                                    {{ loading ? 'Preparing checkout...' : `Upgrade ${selectedPlan?.name}` }}
                                </Button>
                            </div>

                            <p v-if="errorMessage" class="mt-3 text-sm text-destructive">{{ errorMessage }}</p>
                            <p v-if="successMessage" class="mt-3 text-sm text-emerald-600">{{ successMessage }}</p>
                            <div id="plan-paypal-buttons-container" class="mt-3 min-h-11" />
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-sidebar-border/70 bg-card p-5 shadow-sm dark:border-sidebar-border">
                    <h2 class="text-lg font-semibold">Upgrade History</h2>

                    <div v-if="purchases.length === 0" class="mt-4 py-8 text-center text-sm text-muted-foreground">
                        No plan upgrades yet.
                    </div>

                    <div v-else class="mt-4 overflow-x-auto">
                        <table class="w-full min-w-xl text-left text-sm">
                            <thead>
                                <tr class="border-b border-border/60 bg-muted/30">
                                    <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Plan</th>
                                    <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Tier</th>
                                    <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Credit Floor</th>
                                    <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Amount</th>
                                    <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="purchase in purchases" :key="purchase.id" class="border-b border-border/40">
                                    <td class="px-3 py-3 font-medium">{{ purchase.plan_name }}</td>
                                    <td class="px-3 py-3"><span class="capitalize">{{ purchase.tier }}</span></td>
                                    <td class="px-3 py-3">{{ purchase.credits_floor }}</td>
                                    <td class="px-3 py-3">{{ formatPrice(purchase.amount_cents, purchase.currency) }}</td>
                                    <td class="px-3 py-3 text-muted-foreground">{{ purchase.purchased_at ? new Date(purchase.purchased_at).toLocaleDateString() : '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>

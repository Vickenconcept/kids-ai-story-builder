<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

const appName = import.meta.env.VITE_APP_NAME || 'DreamForge AI';
const effectiveDate = 'April 2026';
</script>

<template>
    <Head title="Terms and Conditions" />

    <section class="min-h-screen bg-slate-50 px-4 py-10 md:px-8">
        <div class="mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm md:p-10">
            <div class="mb-6 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <img src="/images/logo-without-bg.png" alt="DreamForge AI" class="h-12 w-auto object-contain" />
                    <span class="text-lg font-bold text-slate-900">{{ appName }}</span>
                </div>
                <Link href="/" class="text-sm font-semibold text-violet-700 hover:underline">Back to Home</Link>
            </div>

            <h1 class="text-3xl font-extrabold text-slate-900 md:text-4xl">Terms and Conditions</h1>
            <p class="mt-2 text-sm text-slate-500">Effective date: {{ effectiveDate }}</p>

            <div class="mt-8 space-y-6 text-sm leading-relaxed text-slate-700">
                <section>
                    <h2 class="text-lg font-bold text-slate-900">1. Acceptance of Terms</h2>
                    <p class="mt-2">
                        By accessing or using {{ appName }}, you agree to these Terms and Conditions. If you do not agree,
                        do not use the service.
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-bold text-slate-900">2. Service Description</h2>
                    <p class="mt-2">
                        {{ appName }} provides tools to generate AI-assisted story content, including text, images,
                        narration, and optional video features. Feature access depends on your plan and available credits.
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-bold text-slate-900">3. Accounts</h2>
                    <p class="mt-2">
                        You are responsible for your account credentials and all activity under your account. You agree to
                        provide accurate information and keep your account secure.
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-bold text-slate-900">4. Payments, Plans, and Credits</h2>
                    <p class="mt-2">
                        Some features require payment, plan upgrades, or usage credits. All prices and plan details are shown
                        at checkout. Credits are consumed when generation tasks are processed. Unless required by law,
                        purchases are non-refundable once consumed.
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-bold text-slate-900">5. Acceptable Use</h2>
                    <p class="mt-2">
                        You agree not to use the service for unlawful, abusive, or harmful content, including intellectual
                        property infringement, hate, violence, or any prohibited activity under applicable law.
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-bold text-slate-900">6. Intellectual Property</h2>
                    <p class="mt-2">
                        The platform, branding, and software remain the property of {{ appName }} and its licensors. You
                        retain ownership of your lawful input content and generated outputs, subject to third-party model and
                        provider terms.
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-bold text-slate-900">7. No Earnings Guarantee</h2>
                    <p class="mt-2">
                        Any monetization examples are illustrative only. We do not guarantee income, sales, or business
                        results.
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-bold text-slate-900">8. Limitation of Liability</h2>
                    <p class="mt-2">
                        To the maximum extent permitted by law, {{ appName }} is provided on an "as is" basis without
                        warranties. We are not liable for indirect, incidental, or consequential damages arising from your use
                        of the service.
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-bold text-slate-900">9. Termination</h2>
                    <p class="mt-2">
                        We may suspend or terminate access for violations of these terms or misuse of the platform.
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-bold text-slate-900">10. Contact</h2>
                    <p class="mt-2">
                        For terms questions, contact: <a href="mailto:vickenconcept@gmail.com" class="font-semibold text-violet-700 hover:underline">vickenconcept@gmail.com</a>.
                    </p>
                </section>
            </div>
        </div>
    </section>
</template>

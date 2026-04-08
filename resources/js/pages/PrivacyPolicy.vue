<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

const appName = import.meta.env.VITE_APP_NAME || 'DreamForge AI';
const effectiveDate = 'April 2026';
</script>

<template>
    <Head title="Privacy Policy" />

    <section class="min-h-screen bg-slate-50 px-4 py-10 md:px-8">
        <div class="mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm md:p-10">
            <div class="mb-6 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <img src="/images/logo-without-bg.png" alt="DreamForge AI" class="h-12 w-auto object-contain" />
                    <span class="text-lg font-bold text-slate-900">{{ appName }}</span>
                </div>
                <Link href="/" class="text-sm font-semibold text-violet-700 hover:underline">Back to Home</Link>
            </div>

            <h1 class="text-3xl font-extrabold text-slate-900 md:text-4xl">Privacy Policy</h1>
            <p class="mt-2 text-sm text-slate-500">Effective date: {{ effectiveDate }}</p>

            <div class="mt-8 space-y-6 text-sm leading-relaxed text-slate-700">
                <section>
                    <h2 class="text-lg font-bold text-slate-900">1. Information We Collect</h2>
                    <p class="mt-2">
                        We may collect account data (name, email), usage data, billing/transaction details, and content you
                        submit to generate stories.
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-bold text-slate-900">2. How We Use Information</h2>
                    <p class="mt-2">
                        We use collected information to operate the service, process payments, provide support, improve
                        product quality, prevent abuse, and communicate important account updates.
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-bold text-slate-900">3. Third-Party Services</h2>
                    <p class="mt-2">
                        We may use third-party providers for infrastructure, AI generation, analytics, and payment processing.
                        These providers may process data as needed to deliver their services.
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-bold text-slate-900">4. Data Retention</h2>
                    <p class="mt-2">
                        We retain data for as long as needed to provide the service, comply with legal obligations, resolve
                        disputes, and enforce agreements.
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-bold text-slate-900">5. Security</h2>
                    <p class="mt-2">
                        We use reasonable technical and organizational safeguards to protect your information. No method of
                        storage or transmission is 100% secure.
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-bold text-slate-900">6. Your Rights</h2>
                    <p class="mt-2">
                        Depending on your location, you may request access, correction, deletion, or export of personal data.
                        Contact us to submit privacy-related requests.
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-bold text-slate-900">7. Children’s Information</h2>
                    <p class="mt-2">
                        {{ appName }} is a content creation tool and is not intended for direct use by children under the age
                        required by local law without parental or guardian supervision.
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-bold text-slate-900">8. Policy Updates</h2>
                    <p class="mt-2">
                        We may update this policy from time to time. Continued use of the service after updates means you
                        accept the revised policy.
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-bold text-slate-900">9. Contact</h2>
                    <p class="mt-2">
                        For privacy questions, contact:
                        <a href="mailto:vickenconcept@gmail.com" class="font-semibold text-violet-700 hover:underline">vickenconcept@gmail.com</a>.
                    </p>
                </section>
            </div>
        </div>
    </section>
</template>

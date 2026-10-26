<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: { type: Array, required: true },
});
</script>

<template>
    <nav v-if="links.length > 3" class="flex items-center justify-between border-t border-gray-200 px-4 py-3 sm:px-0">
        <div class="flex flex-1 justify-between sm:hidden">
            <Link
                v-if="links[0].url"
                :href="links[0].url"
                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
                Previous
            </Link>
            <Link
                v-if="links[links.length - 1].url"
                :href="links[links.length - 1].url"
                class="ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
                Next
            </Link>
        </div>
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-center">
            <div class="isolate inline-flex -space-x-px rounded-md shadow-sm">
                <template v-for="(link, index) in links" :key="index">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="relative inline-flex items-center px-3 py-2 text-sm font-medium border border-gray-300 hover:bg-gray-50"
                        :class="{
                            'z-10 bg-[#1B5E20] text-white border-[#1B5E20] hover:bg-[#2E7D32]': link.active,
                            'bg-white text-gray-700': !link.active,
                            'rounded-l-md': index === 0,
                            'rounded-r-md': index === links.length - 1,
                        }"
                        v-html="link.label"
                        preserve-scroll
                    />
                    <span
                        v-else
                        class="relative inline-flex items-center px-3 py-2 text-sm font-medium border border-gray-300 bg-white text-gray-400 cursor-default"
                        :class="{
                            'rounded-l-md': index === 0,
                            'rounded-r-md': index === links.length - 1,
                        }"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </nav>
</template>

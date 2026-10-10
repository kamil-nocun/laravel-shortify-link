<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { assignmentStatusLabels, billingPeriodLabels, formatPrice } from '@/types';
import type { AssignmentRow, Paginated } from '@/types';

defineProps<{ assignments: Paginated<AssignmentRow> }>();
</script>

<template>
    <div class="space-y-3">
        <h2 class="text-sm font-medium">Package history</h2>
        <p v-if="assignments.data.length === 0" class="text-sm text-muted-foreground">
            No packages yet.
        </p>
        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-left text-sm">
                <thead class="border-b text-muted-foreground">
                    <tr>
                        <th class="p-3">Package</th>
                        <th class="p-3">Price</th>
                        <th class="p-3">From</th>
                        <th class="p-3">To</th>
                        <th class="p-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="assignment in assignments.data" :key="assignment.id" class="border-b last:border-0">
                        <td class="p-3">
                            {{ assignment.package_name }}
                            <span v-if="assignment.billing_period" class="text-muted-foreground">
                                · {{ billingPeriodLabels[assignment.billing_period] }}
                            </span>
                        </td>
                        <td class="p-3">{{ formatPrice(assignment.price) }}</td>
                        <td class="p-3">{{ assignment.starts_at ? new Date(assignment.starts_at).toLocaleDateString() : '—' }}</td>
                        <td class="p-3">{{ assignment.expires_at ? new Date(assignment.expires_at).toLocaleDateString() : '—' }}</td>
                        <td class="p-3">
                            <Badge :variant="assignment.status === 'active' ? 'default' : 'outline'">{{
                                assignmentStatusLabels[assignment.status]
                            }}</Badge>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="assignments.prev_page_url || assignments.next_page_url" class="flex items-center gap-2">
            <Button v-if="assignments.prev_page_url" variant="outline" size="sm" as-child>
                <Link :href="assignments.prev_page_url" preserve-scroll>Previous</Link>
            </Button>
            <Button v-if="assignments.next_page_url" variant="outline" size="sm" as-child>
                <Link :href="assignments.next_page_url" preserve-scroll>Next</Link>
            </Button>
            <span class="text-sm text-muted-foreground">
                Page {{ assignments.current_page }} of {{ assignments.last_page }}
            </span>
        </div>
    </div>
</template>

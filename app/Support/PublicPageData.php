<?php

namespace App\Support;

use App\Enums\BillingPeriod;
use App\Models\Package;
use App\Models\PageVersion;

class PublicPageData
{
    /**
     * The free package first, then the purchasable packages from the cheapest, each with the price of its main billing period.
     *
     * @return list<array{id: string, name: string, link_limit: int, domain_limit: int, allows_custom_alias: bool, is_default: bool, price: int|null, period: string|null}>
     */
    public static function packages(): array
    {
        $listed = Package::query()->get()
            ->filter(fn (Package $package): bool => $package->is_default || $package->availablePeriods() !== [])
            ->map(function (Package $package): array {
                $period = $package->is_default ? null : (in_array(BillingPeriod::Monthly, $package->availablePeriods(), true) ? BillingPeriod::Monthly : $package->availablePeriods()[0]);

                return [
                    'id' => $package->id,
                    'name' => $package->name,
                    'link_limit' => $package->link_limit,
                    'domain_limit' => $package->domain_limit,
                    'allows_custom_alias' => $package->allows_custom_alias,
                    'is_default' => $package->is_default,
                    'price' => $period === null ? null : $package->priceFor($period),
                    'period' => $period?->value,
                ];
            });

        return $listed->sortBy([['is_default', 'desc'], ['price', 'asc']])->values()->all();
    }

    /**
     * Published legal documents for the site footer.
     *
     * @return list<array{title: string, slug: string}>
     */
    public static function legalDocuments(): array
    {
        return ConsentDocuments::current()
            ->map(fn (PageVersion $version): array => ['title' => $version->title, 'slug' => $version->page->slug])
            ->all();
    }
}

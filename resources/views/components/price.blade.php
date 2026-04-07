{{-- ShopSavvy Price Component --}}
{{-- Usage: <x-shopsavvy-price identifier="B0BSHF7WHW" /> --}}

<div class="shopsavvy-price" data-identifier="{{ $identifier }}">
    @if ($errorMessage)
        <div class="shopsavvy-price__error" role="alert" style="padding: 12px 16px; border: 1px solid #f87171; border-radius: 6px; background: #fef2f2; color: #b91c1c; font-size: 14px;">
            Unable to load prices. Please try again later.
        </div>
    @elseif (empty($offers))
        <div class="shopsavvy-price__empty" style="padding: 12px 16px; color: #6b7280; font-size: 14px;">
            No prices available for this product.
        </div>
    @else
        <div class="shopsavvy-price__list" style="display: flex; flex-direction: column; gap: 8px;">
            @foreach ($offers as $offer)
                @php
                    $price       = isset($offer['price'])   ? '$' . number_format((float) $offer['price'], 2)   : null;
                    $msrp        = isset($offer['msrp'])    ? '$' . number_format((float) $offer['msrp'], 2)    : null;
                    $retailer    = $offer['retailer'] ?? $offer['store'] ?? 'Retailer';
                    $url         = $offer['url'] ?? null;
                    $inStock     = $offer['in_stock'] ?? true;
                    $condition   = $offer['condition'] ?? 'New';
                @endphp
                <div class="shopsavvy-price__offer"
                     style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; gap: 12px;">
                    <div class="shopsavvy-price__retailer" style="font-weight: 600; font-size: 15px; color: #111827; flex: 1; min-width: 0;">
                        {{ $retailer }}
                        @if ($condition && strtolower($condition) !== 'new')
                            <span style="font-size: 12px; font-weight: 400; color: #6b7280; margin-left: 4px;">({{ $condition }})</span>
                        @endif
                    </div>

                    <div class="shopsavvy-price__pricing" style="display: flex; flex-direction: column; align-items: flex-end; gap: 2px;">
                        @if ($price)
                            <span class="shopsavvy-price__amount" style="font-size: 18px; font-weight: 700; color: #059669;">{{ $price }}</span>
                        @endif
                        @if ($msrp && $price && $msrp !== $price)
                            <span class="shopsavvy-price__msrp" style="font-size: 12px; color: #9ca3af; text-decoration: line-through;">{{ $msrp }}</span>
                        @endif
                    </div>

                    @if (!$inStock)
                        <span class="shopsavvy-price__stock" style="font-size: 12px; color: #ef4444; white-space: nowrap;">Out of stock</span>
                    @elseif ($url)
                        <a class="shopsavvy-price__cta"
                           href="{{ $url }}"
                           target="_blank"
                           rel="noopener noreferrer sponsored"
                           style="display: inline-block; padding: 8px 16px; background: #2563eb; color: #fff; border-radius: 6px; font-size: 13px; font-weight: 600; text-decoration: none; white-space: nowrap;">
                            Buy Now
                        </a>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="shopsavvy-price__footer" style="margin-top: 8px; font-size: 12px; color: #9ca3af; text-align: right;">
            Prices via <a href="https://shopsavvy.com" target="_blank" rel="noopener" style="color: #6b7280;">ShopSavvy</a>
        </div>
    @endif
</div>

{{-- ShopSavvy Search Component --}}
{{-- Usage: <x-shopsavvy-search query="airpods" /> --}}

<div class="shopsavvy-search" data-query="{{ $query }}">
    @if ($errorMessage)
        <div class="shopsavvy-search__error" role="alert" style="padding: 12px 16px; border: 1px solid #f87171; border-radius: 6px; background: #fef2f2; color: #b91c1c; font-size: 14px;">
            Unable to load search results. Please try again later.
        </div>
    @elseif (empty($products))
        <div class="shopsavvy-search__empty" style="padding: 12px 16px; color: #6b7280; font-size: 14px;">
            No products found for &ldquo;{{ $query }}&rdquo;.
        </div>
    @else
        <div class="shopsavvy-search__header" style="margin-bottom: 16px;">
            <p style="font-size: 14px; color: #6b7280; margin: 0;">
                Showing {{ count($products) }} of {{ number_format($total) }} results for
                <strong style="color: #111827;">&ldquo;{{ $query }}&rdquo;</strong>
            </p>
        </div>

        <div class="shopsavvy-search__grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px;">
            @foreach ($products as $product)
                @php
                    $title       = $product['title'] ?? $product['name'] ?? 'Unknown Product';
                    $brand       = $product['brand'] ?? null;
                    $image       = $product['image'] ?? $product['image_url'] ?? null;
                    $lowestPrice = $product['lowest_price'] ?? $product['price'] ?? null;
                    $price       = $lowestPrice !== null ? '$' . number_format((float) $lowestPrice, 2) : null;
                    $id          = $product['id'] ?? $product['asin'] ?? $product['upc'] ?? null;
                    $productUrl  = $product['url'] ?? ($id ? "https://shopsavvy.com/products/{$id}" : null);
                @endphp
                <div class="shopsavvy-search__product" style="display: flex; flex-direction: column; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; background: #fff;">
                    @if ($image)
                        <div class="shopsavvy-search__image" style="aspect-ratio: 1; overflow: hidden; background: #f9fafb; display: flex; align-items: center; justify-content: center; padding: 12px;">
                            <img src="{{ $image }}"
                                 alt="{{ $title }}"
                                 loading="lazy"
                                 style="max-width: 100%; max-height: 100%; object-fit: contain;" />
                        </div>
                    @else
                        <div class="shopsavvy-search__image-placeholder" style="aspect-ratio: 1; background: #f3f4f6; display: flex; align-items: center; justify-content: center;">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5">
                                <rect x="3" y="3" width="18" height="18" rx="2"/>
                                <circle cx="8.5" cy="8.5" r="1.5"/>
                                <polyline points="21 15 16 10 5 21"/>
                            </svg>
                        </div>
                    @endif

                    <div class="shopsavvy-search__info" style="padding: 12px; flex: 1; display: flex; flex-direction: column; gap: 4px;">
                        @if ($brand)
                            <div class="shopsavvy-search__brand" style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280;">
                                {{ $brand }}
                            </div>
                        @endif

                        <div class="shopsavvy-search__title" style="font-size: 14px; font-weight: 500; color: #111827; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                            {{ $title }}
                        </div>

                        <div style="flex: 1;"></div>

                        @if ($price)
                            <div class="shopsavvy-search__price" style="font-size: 17px; font-weight: 700; color: #059669; margin-top: 8px;">
                                {{ $price }}
                            </div>
                        @endif

                        @if ($productUrl)
                            <a class="shopsavvy-search__link"
                               href="{{ $productUrl }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               style="display: block; margin-top: 8px; padding: 8px 0; text-align: center; background: #f3f4f6; color: #374151; border-radius: 6px; font-size: 13px; font-weight: 600; text-decoration: none;">
                                Compare Prices
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="shopsavvy-search__footer" style="margin-top: 12px; font-size: 12px; color: #9ca3af; text-align: right;">
            Search results via <a href="https://shopsavvy.com" target="_blank" rel="noopener" style="color: #6b7280;">ShopSavvy</a>
        </div>
    @endif
</div>

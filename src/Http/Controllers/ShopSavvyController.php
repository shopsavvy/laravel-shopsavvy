<?php

declare(strict_types=1);

namespace ShopSavvy\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ShopSavvy\Laravel\Exceptions\ShopSavvyAuthenticationException;
use ShopSavvy\Laravel\Exceptions\ShopSavvyException;
use ShopSavvy\Laravel\Exceptions\ShopSavvyNotFoundException;
use ShopSavvy\Laravel\Exceptions\ShopSavvyRateLimitException;
use ShopSavvy\Laravel\Exceptions\ShopSavvyValidationException;
use ShopSavvy\Laravel\ShopSavvyManager;

class ShopSavvyController extends Controller
{
    public function __construct(private ShopSavvyManager $shopsavvy) {}

    /**
     * GET /search?q=...&limit=...&offset=...
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q'      => ['required', 'string', 'min:1', 'max:200'],
            'limit'  => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ]);

        return $this->respond(fn () => $this->shopsavvy->search(
            $request->string('q')->value(),
            (int) $request->input('limit', 10),
            (int) $request->input('offset', 0)
        ));
    }

    /**
     * GET /offers/{identifier}?retailer=...
     */
    public function offers(Request $request, string $identifier): JsonResponse
    {
        $request->validate([
            'retailer' => ['sometimes', 'string'],
        ]);

        return $this->respond(fn () => $this->shopsavvy->offers(
            $identifier,
            $request->string('retailer')->value() ?: null
        ));
    }

    /**
     * GET /history/{identifier}?start=...&end=...&retailer=...
     */
    public function history(Request $request, string $identifier): JsonResponse
    {
        $request->validate([
            'start'    => ['required', 'date_format:Y-m-d'],
            'end'      => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
            'retailer' => ['sometimes', 'string'],
        ]);

        return $this->respond(fn () => $this->shopsavvy->priceHistory(
            $identifier,
            $request->string('start')->value(),
            $request->string('end')->value(),
            $request->string('retailer')->value() ?: null
        ));
    }

    /**
     * GET /product/{identifier}
     */
    public function product(Request $request, string $identifier): JsonResponse
    {
        return $this->respond(fn () => $this->shopsavvy->product($identifier));
    }

    /**
     * GET /deals?limit=...
     */
    public function deals(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return $this->respond(fn () => $this->shopsavvy->deals(
            (int) $request->input('limit', 10)
        ));
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function respond(callable $action): JsonResponse
    {
        try {
            return response()->json($action());
        } catch (ShopSavvyAuthenticationException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        } catch (ShopSavvyNotFoundException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (ShopSavvyValidationException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (ShopSavvyRateLimitException $e) {
            return response()->json(['error' => $e->getMessage()], 429);
        } catch (ShopSavvyException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }
}

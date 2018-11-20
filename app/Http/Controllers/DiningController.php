<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\DiningService;
use App\Http\Resources\DiningResource;
use Illuminate\Support\Facades\Auth;

/**
 * Class DiningController
 * @package App\Http\Controllers
 */
class DiningController extends Controller
{
    private $diningService;

    public function __construct(DiningService $diningService)
    {
        $this->diningService = $diningService;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function index(Request $request): JsonResponse
    {

        $location = $request->get('location') ?? '';
        $price = $request->get('price') ?? '';
        $categories = $request->get('categories') ?? '';
        $sortby = $request->get('sortby') ?? '';
        $attributes = $request->get('attributes') ?? '';

        $open_now = $request->get('open_now') ?? '';
        $restaurants = $this->diningService->search($location, $price,$categories,$sortby,$attributes,$open_now);

        return DiningResource::collection(collect($restaurants))->response();



    }

}

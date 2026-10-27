<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ArtistResource;
use App\Models\ArtistProfile;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ArtistController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ArtistResource::collection(
            ArtistProfile::query()->approved()->withCount('publishedArtworks')->orderBy('display_name')->paginate(30),
        );
    }

    public function show(ArtistProfile $artistProfile): ArtistResource
    {
        abort_unless($artistProfile->isApproved(), 404);

        return new ArtistResource($artistProfile->loadCount('publishedArtworks'));
    }
}

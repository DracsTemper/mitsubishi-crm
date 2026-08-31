<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVehicleRequest;
use App\Http\Requests\Admin\UpdateVehicleRequest;
use App\Models\Dealer;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public function index(): View
    {
        return view('pages.inventory', [
            'vehicles' => Vehicle::query()->with('demoAllocations.dealer')->orderBy('name')->get(),
            'statuses' => Vehicle::STATUSES,
        ]);
    }

    public function create(): View
    {
        return view('pages.vehicles.create', $this->formData());
    }

    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        $attributes = $request->safe()->except('image');
        if ($request->hasFile('image')) {
            $attributes['image'] = $this->storeImage($request->file('image'));
        }

        $vehicle = Vehicle::query()->create($attributes);

        return redirect()->route('vehicles.show', $vehicle)->with('success', 'Vehicle created successfully.');
    }

    public function show(Vehicle $vehicle): View
    {
        $vehicle->load('demoAllocations.dealer');

        return view('pages.vehicles.show', [
            'vehicle' => $vehicle,
            'dealers' => Dealer::query()->orderBy('name')->get(),
        ]);
    }

    public function edit(Vehicle $vehicle): View
    {
        return view('pages.vehicles.edit', $this->formData() + ['vehicle' => $vehicle]);
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $attributes = $request->safe()->except('image');
        $oldImage = $vehicle->image;
        if ($request->hasFile('image')) {
            $attributes['image'] = $this->storeImage($request->file('image'));
        }

        $vehicle->update($attributes);
        if (isset($attributes['image'])) {
            $this->deleteManagedImage($oldImage);
        }

        return redirect()->route('vehicles.show', $vehicle)->with('success', 'Vehicle updated successfully.');
    }

    public function destroy(Request $request, Vehicle $vehicle): RedirectResponse|JsonResponse
    {
        $image = $vehicle->image;
        $vehicle->delete();
        $this->deleteManagedImage($image);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Vehicle deleted successfully.',
                'data' => ['id' => $vehicle->id],
            ]);
        }

        return redirect()->route('vehicles.index')->with('success', 'Vehicle deleted successfully.');
    }

    /** @return array{dealers: \Illuminate\Database\Eloquent\Collection<int, Dealer>, statuses: list<string>} */
    private function formData(): array
    {
        return [
            'dealers' => Dealer::query()->orderBy('name')->get(),
            'statuses' => Vehicle::STATUSES,
        ];
    }

    private function storeImage(UploadedFile $image): string
    {
        $relativeDirectory = 'assets/images/vehicles/uploads';
        $absoluteDirectory = public_path($relativeDirectory);
        File::ensureDirectoryExists($absoluteDirectory);
        $filename = Str::uuid().'.'.$image->extension();
        $image->move($absoluteDirectory, $filename);

        return $relativeDirectory.'/'.$filename;
    }

    private function deleteManagedImage(?string $image): void
    {
        if ($image && str_starts_with($image, 'assets/images/vehicles/uploads/')) {
            File::delete(public_path($image));
        }
    }
}

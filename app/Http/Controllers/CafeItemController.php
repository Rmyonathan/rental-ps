<?php

namespace App\Http\Controllers;

use App\Models\CafeItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CafeItemController extends Controller
{
    public function index()
    {
        $cafeItems = CafeItem::with('stock')->orderBy('category')->orderBy('name')->get();
        return view('cafe.items.index', compact('cafeItems'));
    }

    public function create()
    {
        return view('cafe.items.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|in:food,drink,snack,dessert',
            'price' => 'required|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'preparation_time' => 'required|integer|min:1|max:60'
        ]);

        $data = $request->all();
        
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('cafe-items', 'public');
        }

        CafeItem::create($data);

        return redirect()->route('cafe.items.index')
            ->with('success', 'Menu item created successfully!');
    }

    public function edit(CafeItem $cafeItem)
    {
        return view('cafe.items.edit', compact('cafeItem'));
    }

    public function update(Request $request, CafeItem $cafeItem)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|in:food,drink,snack,dessert',
            'price' => 'required|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'preparation_time' => 'required|integer|min:1|max:60'
        ]);

        $data = $request->all();
        
        if ($request->hasFile('image')) {
            // Delete old image
            if ($cafeItem->image) {
                Storage::disk('public')->delete($cafeItem->image);
            }
            $data['image'] = $request->file('image')->store('cafe-items', 'public');
        }

        $cafeItem->update($data);

        return redirect()->route('cafe.items.index')
            ->with('success', 'Menu item updated successfully!');
    }

    public function destroy(CafeItem $cafeItem)
    {
        if ($cafeItem->image) {
            Storage::disk('public')->delete($cafeItem->image);
        }
        
        $cafeItem->delete();

        return redirect()->route('cafe.items.index')
            ->with('success', 'Menu item deleted successfully!');
    }
}

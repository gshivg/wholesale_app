<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
class Landing extends Component
{
    public function render()
    {
        return view('livewire.landing', [
            'categories' => Category::where('is_active', true)->withCount('products')->orderBy('sort_order')->get(),
            'featured' => Product::active()->where('is_featured', true)->with('category')->limit(8)->get(),
            'productCount' => Product::active()->count(),
        ]);
    }
}

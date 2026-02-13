<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Area;
use App\Models\Branch;

class RecipeBookController extends Controller
{
    public function index()
    {
        return view('recipe_book.index');
    }

    public function store(Request $request)
    {
      return view('recipe_book.index');
    }

    public function edit(Request $request)
    {
        return view('recipe_book.edit');
    }

    public function update(Request $request, Area $area)
    {
        return view('recipe_book.index');
    }

    public function destroy(Area $area)
    {
        return view('recipe_book.index');
    }
}

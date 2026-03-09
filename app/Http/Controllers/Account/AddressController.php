<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index()
    {
        $addresses = auth()->user()->addresses()->shipping()->orderBy('is_default', 'desc')->orderBy('id')->get();
        return view('account.addresses.index', ['addresses' => $addresses]);
    }

    public function create()
    {
        return view('account.addresses.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'full_address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $validated['user_id'] = auth()->id();
        $validated['type'] = 'shipping';
        $validated['is_default'] = $request->boolean('is_default');

        if ($validated['is_default']) {
            auth()->user()->addresses()->shipping()->update(['is_default' => false]);
        }

        Address::create($validated);
        return redirect()->route('account.addresses.index')->with('success', 'Адрес добавлен.');
    }

    public function edit(Address $address)
    {
        abort_if($address->user_id !== auth()->id(), 403);
        return view('account.addresses.edit', ['address' => $address]);
    }

    public function update(Request $request, Address $address)
    {
        abort_if($address->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'full_address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $validated['is_default'] = $request->boolean('is_default');
        if ($validated['is_default']) {
            auth()->user()->addresses()->shipping()->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        $address->update($validated);
        return redirect()->route('account.addresses.index')->with('success', 'Адрес обновлён.');
    }

    public function destroy(Address $address)
    {
        abort_if($address->user_id !== auth()->id(), 403);
        $address->delete();
        return redirect()->route('account.addresses.index')->with('success', 'Адрес удалён.');
    }
}

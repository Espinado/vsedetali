<?php

namespace App\Livewire\Storefront;

use App\Models\Product;
use Livewire\Component;

class HeaderSearch extends Component
{
    public string $query = '';

    /** Показывать выпадающий список подсказок (скрывается после перехода по ссылке). */
    public bool $resultsPanelOpen = true;

    public function mount(): void
    {
        $this->query = (string) request('search', '');
    }

    public function updatedQuery(): void
    {
        $this->resultsPanelOpen = true;
    }

    public function closeResultsPanel(): void
    {
        $this->resultsPanelOpen = false;
    }

    public function getResultsProperty()
    {
        $term = trim($this->query);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        $like = '%' . $term . '%';

        return Product::active()
            ->with(['brand', 'images', 'stocks', 'oemNumbers', 'crossNumbers'])
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhereHas('oemNumbers', function ($q) use ($like) {
                        $q->where('oem_number', 'like', $like);
                    })
                    ->orWhereHas('crossNumbers', function ($q) use ($like) {
                        $q->where('cross_number', 'like', $like)
                            ->orWhere('manufacturer_name', 'like', $like);
                    });
            })
            ->orderBy('name')
            ->limit(6)
            ->get();
    }

    public function search(): void
    {
        $this->closeResultsPanel();

        $term = trim($this->query);

        if ($term === '') {
            $this->redirect(route('catalog'), navigate: true);

            return;
        }

        $this->redirect(route('catalog', ['search' => $term]), navigate: true);
    }

    public function clearSearch(): void
    {
        $this->query = '';
        $this->resultsPanelOpen = true;
    }

    public function render()
    {
        return view('livewire.storefront.header-search');
    }
}

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    .filament-swal-popup {
        font-size: 1rem !important;
        line-height: 1.5;
    }
    @media (max-width: 639px) {
        .filament-swal-popup {
            width: calc(100vw - 1.5rem) !important;
            margin: 0.75rem auto !important;
        }
    }
    .filament-swal-actions {
        flex-wrap: wrap !important;
        gap: 0.5rem !important;
        justify-content: center !important;
        width: 100%;
    }
    .filament-swal-btn {
        min-height: 44px;
        padding: 0.5rem 1rem !important;
        border-radius: 0.5rem !important;
        font-weight: 600 !important;
        font-size: 0.9375rem !important;
    }
    .filament-swal-btn-confirm {
        background: #ea580c !important;
        color: #fff !important;
        border: none !important;
    }
    .filament-swal-btn-cancel {
        background: #e7e5e4 !important;
        color: #1c1917 !important;
        border: none !important;
    }
</style>
<script>
    {!! \App\Filament\Support\FilamentSweetAlert::mixinScript() !!}
</script>

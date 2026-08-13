<x-app-layout>
    <x-slot name="header">
        Dashboard
    </x-slot>

    @if(auth()->user()->isAdmin())
        @include('dashboards.admin')
    @elseif(auth()->user()->isDoctor())
        @include('dashboards.doctor')
    @else
        @include('dashboards.patient')
        <livewire:patient.testimonial-popup />
    @endif
</x-app-layout>

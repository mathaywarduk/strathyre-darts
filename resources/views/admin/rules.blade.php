<x-layouts.admin title="Rules">
    <h1 class="h2" style="margin-top: 16px">Rules</h1>
    <p class="sub">The league’s written rules, shown on the public Rules page. @if (\App\Support\RichText::hasContent($rules))<a href="{{ route('rules') }}">View the page</a>@else The Rules page stays hidden until you add some text.@endif</p>

    <form method="POST" action="{{ route('admin.rules.update') }}" class="card stack" data-save-shortcut>
        @csrf @method('PUT')
        <x-rich-editor name="content" label="Rules" :value="old('content', $rules)" />
        <button class="btn" type="submit">Save rules</button>
    </form>
</x-layouts.admin>

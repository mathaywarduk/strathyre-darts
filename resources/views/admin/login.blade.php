<x-layouts.admin title="Log in">
    <form method="POST" action="{{ route('admin.login') }}" class="card stack" style="max-width: 360px; margin: 32px auto">
        @csrf
        <h1 class="h2">Log in</h1>
        <div>
            <label class="label" for="username">Username</label>
            <input class="field" id="username" name="username" value="{{ old('username') }}" required autofocus autocomplete="username" autocapitalize="none">
        </div>
        <div>
            <label class="label" for="password">Password</label>
            <input class="field" id="password" name="password" type="password" required autocomplete="current-password">
        </div>
        <button class="btn" type="submit" style="width: 100%">Log in</button>
    </form>
</x-layouts.admin>

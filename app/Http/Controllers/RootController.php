<?php

namespace App\Http\Controllers;

use App\Services\Site\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * `/` on Arcadia: to the landing page in the language this browser last used here, else Spanish
 * (`Site::localeFor()`). A 302, since the answer changes with the visitor's choice.
 */
class RootController
{
    public function __invoke(Request $request, Site $site): RedirectResponse
    {
        return redirect($site->homePath($site->localeFor($request->session()->get('locale'))));
    }
}

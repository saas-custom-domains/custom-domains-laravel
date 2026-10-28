<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\CustomDomain;
use App\Services\DomainService;
use App\Support\Hosts;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

// This example has no login. In your app, check that the signed-in user
// owns the board (and the domain) in every one of these actions.
class DashboardController extends Controller
{
    public function __construct(protected DomainService $domains) {}

    public function index(): View
    {
        return view('dashboard', [
            'boards' => Board::with(['customDomains' => fn ($q) => $q->orderBy('id')])->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request, Board $board): RedirectResponse
    {
        $host = Hosts::normalize($request->input('host'));
        $dnsContact = $request->input('dns_contact') ?: null;
        $fail = fn (string $message) => back()
            ->withErrors(['host' => $message], "board{$board->id}")
            ->withInput();

        if (! Hosts::isValid($host)) {
            return $fail("\"{$host}\" isn't a valid domain.");
        }
        if (Hosts::isReserved($host)) {
            return $fail("That's one of our domains.");
        }
        if ($dnsContact !== null && ! filter_var($dnsContact, FILTER_VALIDATE_EMAIL)) {
            return $fail('Enter a valid email for the DNS contact, or leave it empty.');
        }

        try {
            $this->domains->add($board, $host, $dnsContact);
        } catch (UniqueConstraintViolationException) {
            return $fail("{$host} is already connected to a job board.");
        } catch (RequestException $e) {
            Log::error('Adding a custom domain failed', ['host' => $host, 'response' => $e->response->body()]);

            return $fail($e->response->json('error') ?? 'Could not add the domain. Try again.');
        }

        return to_route('dashboard');
    }

    public function check(CustomDomain $domain): RedirectResponse
    {
        $this->domains->check($domain);

        return to_route('dashboard');
    }

    public function destroy(CustomDomain $domain): RedirectResponse
    {
        $this->domains->remove($domain);

        return to_route('dashboard');
    }
}

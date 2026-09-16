<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Addresses name things; the HTTP method says what is being done to them.
 *
 * This is checked by machine because it is the kind of thing that only ever
 * drifts one route at a time. Somebody needs an endpoint in a hurry, writes
 * POST /experts/{expert}/deactivate because that is what it does, and the
 * convention is a little bit less true than it was - until a panel asks and
 * the honest answer is "mostly".
 *
 * Two rules, both cheap to keep:
 *
 *   1. The last part of a path is a noun. What happens to it is the method:
 *      DELETE to remove, PUT/PATCH to change, POST to create.
 *   2. Nothing that changes data answers to GET.
 */
class RoutesFollowRestConventionsTest extends TestCase
{
    /**
     * Words that describe an action rather than a thing.
     *
     * Deliberately not exhaustive - it only has to be good enough to catch
     * the next one somebody writes by hand.
     */
    private const ACTION_WORDS = [
        'accept', 'activate', 'add', 'apply', 'approve', 'assign', 'cancel',
        'check', 'choose', 'confirm', 'create', 'deactivate', 'decline',
        'delete', 'disconnect', 'download', 'export', 'generate', 'import',
        'reactivate', 'refresh', 'reject', 'remind', 'remove', 'resend',
        'restore', 'run', 'send', 'submit', 'suggest', 'toggle', 'unassign',
        'update', 'upload', 'verify',
    ];

    /**
     * The ones that were already here, and why each is allowed to stay.
     *
     * This list may shrink and must never grow. A new entry means a new
     * endpoint named after a verb, which is the thing being prevented -
     * fixing one of these, on the other hand, just makes the test pass more
     * easily, so nobody has to come back and edit this to clean something up.
     */
    private const KNOWN = [
        // OAuth. The address a person is sent to in order to grant access is
        // conventionally named for the act, in every provider's own docs.
        'admin/gdrive/connect',

        // A dry run of an upload: it reads a spreadsheet and answers with
        // what would be created, writing nothing.
        'admin/rosters/experts/preview',
        'capsa/rosters/experts/preview',
        'capsa/rosters/students/preview',

        // Not yet renamed. Each wants a noun - /selection, /suggestions,
        // /consultation - and each is somebody else's screen to retest.
        'pre-proposal/{preProposal}/choose',
        'student/consultation/refresh',
    ];

    /** @return list<string> */
    private function offendingUris(): array
    {
        $found = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            // Framework and vendor plumbing is not ours to name.
            if (str_starts_with($uri, '_') || str_contains($uri, 'sanctum')
                || str_contains($uri, 'ignition')) {
                continue;
            }

            $last = last(explode('/', rtrim($uri, '/')));

            // A bound parameter is not a name we chose.
            if (str_starts_with($last, '{')) {
                continue;
            }

            if (in_array(mb_strtolower($last), self::ACTION_WORDS, true)) {
                $found[] = $uri;
            }
        }

        return array_values(array_unique($found));
    }

    public function test_no_new_endpoint_is_named_after_a_verb(): void
    {
        $unexpected = array_diff($this->offendingUris(), self::KNOWN);

        $this->assertSame([], array_values($unexpected), implode("\n", [
            'These addresses are named for what they do rather than what they are.',
            'Give the last segment a noun and let the method carry the verb -',
            'PUT .../activation instead of PUT .../reactivate, for example.',
            '',
            ...$unexpected,
        ]));
    }

    /**
     * A GET must be safe to repeat, prefetch, bookmark or crawl. Anything
     * that writes has to sit behind POST, PUT, PATCH or DELETE.
     */
    public function test_nothing_that_writes_answers_to_get(): void
    {
        $writing = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $action = $route->getActionName();

            foreach (['store', 'update', 'destroy', 'delete'] as $writer) {
                if (str_ends_with($action, '@'.$writer)) {
                    $writing[] = $route->uri().' -> '.$action;
                }
            }
        }

        $this->assertSame([], $writing);
    }

    /**
     * The endpoints behind the work done in this round, spelled out. Renaming
     * one is a breaking change for whoever calls it, so it should have to be
     * done on purpose.
     */
    public function test_the_endpoints_touched_this_round_keep_their_shape(): void
    {
        $expected = [
            // A team reading back its own uploaded pre-proposal.
            'student.pre-proposal.document' => ['GET', 'pre-proposal/{preProposal}/document'],
            // Answering an invitation - one address, the status in the body.
            'invitations.update' => ['PATCH', 'invitations/{invitation}'],
            // Naming a CaPSA.
            'admin.experts.capsa.store' => ['POST', 'admin/experts/capsa'],
            // Switching an account off, and back on again.
            'admin.experts.destroy' => ['DELETE', 'admin/experts/{expert}'],
            'admin.experts.activation.update' => ['PUT', 'admin/experts/{expert}/activation'],
            'capsa.experts.activation.update' => ['PUT', 'capsa/experts/{expert}/activation'],
        ];

        foreach ($expected as $name => [$method, $uri]) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "the route {$name} exists");
            $this->assertSame($uri, $route->uri(), "{$name} is addressed as {$uri}");
            $this->assertContains($method, $route->methods(), "{$name} answers to {$method}");
        }
    }
}

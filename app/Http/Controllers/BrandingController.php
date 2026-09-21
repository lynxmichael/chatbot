<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Identité visuelle d'une entreprise.
 *
 * Ce que le client final voit : le logo et la couleur du widget posé
 * sur son site, le nom qui apparaît dans les emails. Une entreprise qui
 * installe un widget aux couleurs de quelqu'un d'autre ne le garde pas.
 */
class BrandingController extends Controller
{
    public function index(Request $request)
    {
        $organization = $this->organization($request);

        return Inertia::render('Branding/Index', [
            'branding' => [
                'name' => $organization->name,
                'logo' => $organization->logo_path
                    ? asset('storage/' . $organization->logo_path)
                    : null,
                'brand_color' => $organization->brand_color ?: '#4f46e5',
                'support_email' => $organization->support_email,
                'welcome_message' => $organization->welcome_message,
            ],

            'widget_token' => $organization->widget_token,
            'widget_url' => url('/widget/widget.js'),
            'api_url' => url('/api'),

            /*
             * Lien pour essayer le widget sur un site fictif, sans
             * l'avoir encore installé nulle part.
             */
            'demo_url' => route('demo.show', $organization->widget_token),
        ]);
    }

    public function update(Request $request)
    {
        $organization = $this->organization($request);

        $validated = $request->validate([
            'brand_color' => [
                'nullable',
                'string',
                /*
                 * Format hexadécimal uniquement : la valeur est injectée
                 * dans le style du widget, il ne doit rien pouvoir
                 * passer d'autre.
                 */
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],

            'support_email' => ['nullable', 'email', 'max:255'],

            'welcome_message' => ['nullable', 'string', 'max:300'],

            'logo' => [
                'nullable',
                'image',
                'mimes:png,jpg,jpeg,webp,svg',
                'max:1024',
            ],

            'remove_logo' => ['boolean'],
        ]);

        $changes = [
            'brand_color' => $validated['brand_color'] ?? null,
            'support_email' => $validated['support_email'] ?? null,
            'welcome_message' => $validated['welcome_message'] ?? null,
        ];

        if ($request->boolean('remove_logo') && $organization->logo_path) {
            Storage::disk('public')->delete($organization->logo_path);

            $changes['logo_path'] = null;
        }

        if ($request->hasFile('logo')) {
            /*
             * L'ancien logo est supprimé : sans cela, chaque envoi
             * laisse un fichier orphelin sur le disque.
             */
            if ($organization->logo_path) {
                Storage::disk('public')->delete($organization->logo_path);
            }

            $changes['logo_path'] = $request->file('logo')->store(
                'logos/' . $organization->id,
                'public'
            );
        }

        $organization->update($changes);

        return back()->with('success', 'Identité visuelle enregistrée.');
    }

    private function organization(Request $request)
    {
        $user = $request->user();

        abort_unless($user->hasAbility('branding.manage'), 403);

        $organization = $user->organization;

        abort_unless($organization, 404);

        return $organization;
    }
}

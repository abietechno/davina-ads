<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanySettingController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = CompanySetting::first();

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'company_name' => 'sometimes|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
            'login_banner' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:5120',
        ]);

        $settings = CompanySetting::first();

        if ($request->has('company_name')) {
            $settings->company_name = $request->company_name;
        }

        if ($request->has('tagline')) {
            $settings->tagline = $request->tagline;
        }

        if ($request->hasFile('logo')) {
            // Delete old logo
            if ($settings->logo_path) {
                \Storage::disk('public')->delete($settings->logo_path);
            }
            $settings->logo_path = $request->file('logo')->store('branding', 'public');
        }

        if ($request->hasFile('login_banner')) {
            if ($settings->login_banner_path) {
                \Storage::disk('public')->delete($settings->login_banner_path);
            }
            $settings->login_banner_path = $request->file('login_banner')->store('branding', 'public');
        }

        $settings->save();

        return response()->json([
            'success' => true,
            'message' => 'Company settings updated.',
            'data' => $settings,
        ]);
    }
}

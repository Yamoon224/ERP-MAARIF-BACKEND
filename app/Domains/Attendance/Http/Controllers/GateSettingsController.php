<?php

namespace App\Domains\Attendance\Http\Controllers;

use App\Domains\Attendance\Http\Requests\UpdateGateSettingsRequest;
use App\Domains\Attendance\Http\Resources\AttendanceGateSettingResource;
use App\Domains\Attendance\Services\GateCheckInService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/** Reglage du pointage geolocalise au portail (cahier des charges - pointage par QR code, option 1). */
class GateSettingsController extends Controller
{
    public function __construct(private readonly GateCheckInService $gate) {}

    public function show(): AttendanceGateSettingResource
    {
        return new AttendanceGateSettingResource($this->gate->settings());
    }

    public function update(UpdateGateSettingsRequest $request): AttendanceGateSettingResource
    {
        return new AttendanceGateSettingResource($this->gate->updateSettings($request->validated()));
    }

    public function regenerateToken(): AttendanceGateSettingResource
    {
        return new AttendanceGateSettingResource($this->gate->regenerateToken());
    }

    public function qr(): Response
    {
        return response($this->gate->qrImage(), 200, ['Content-Type' => 'image/png']);
    }

    public function poster(): Response
    {
        return response($this->gate->posterPdf(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="affiche-pointage-portail.pdf"',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewAdminRegistrationRequest;
use App\Http\Requests\StoreAdminRegistrationRequest;
use App\Http\Requests\UpdateAdminRegistrationRequest;
use App\Http\Resources\AdminRegistrationRequestResource;
use App\Http\Resources\PricingPlanResource;
use App\Models\AdminRegistrationRequest;
use App\Models\PricingPlan;
use App\Services\AdminRegistrationApprovalService;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AdminRegistrationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('AdminRegistrations/Create', [
            'entityTypes' => $this->entityTypes(),
            'plans' => $this->registrationPlans(),
            'selectedPlan' => request('plan'),
        ]);
    }

    public function store(StoreAdminRegistrationRequest $request): RedirectResponse
    {
        $registration = AdminRegistrationRequest::create([
            ...$request->safe()->except(['password', 'password_confirmation']),
            'password' => Hash::make($request->validated('password')),
            'status' => AdminRegistrationRequest::STATUS_PENDING,
        ]);

        $this->notifyRegistrationReceived($registration);

        return redirect()
            ->route('admin-registrations.thank-you')
            ->with('success', 'Registration submitted for review.');
    }

    public function thankYou(): Response
    {
        return Inertia::render('AdminRegistrations/ThankYou');
    }

    public function index(): Response
    {
        return Inertia::render('AdminRegistrations/Index', [
            'registrations' => AdminRegistrationRequestResource::collection(
                AdminRegistrationRequest::query()
                    ->with('pricingPlan')
                    ->latest()
                    ->get()
            ),
        ]);
    }

    public function show(AdminRegistrationRequest $adminRegistration): Response
    {
        return Inertia::render('AdminRegistrations/Show', [
            'registration' => AdminRegistrationRequestResource::make($adminRegistration->load('pricingPlan')),
        ]);
    }

    public function edit(AdminRegistrationRequest $adminRegistration): Response
    {
        abort_if($adminRegistration->status !== AdminRegistrationRequest::STATUS_PENDING, 403);

        return Inertia::render('AdminRegistrations/Edit', [
            'registration' => AdminRegistrationRequestResource::make($adminRegistration->load('pricingPlan')),
            'entityTypes' => $this->entityTypes(),
            'plans' => $this->registrationPlans(includeInactive: true),
        ]);
    }

    public function update(UpdateAdminRegistrationRequest $request, AdminRegistrationRequest $adminRegistration): RedirectResponse
    {
        abort_if($adminRegistration->status !== AdminRegistrationRequest::STATUS_PENDING, 403);

        $adminRegistration->update($request->safe()->all());

        return redirect()
            ->route('admin-registrations.show', $adminRegistration)
            ->with('success', 'Application updated.');
    }

    public function approve(
        ReviewAdminRegistrationRequest $request,
        AdminRegistrationRequest $adminRegistration,
        AdminRegistrationApprovalService $approvalService
    ): RedirectResponse {
        $approvalService->approve($adminRegistration, $request->user(), $request->validated('review_notes'));

        return redirect()
            ->route('admin-registrations.show', $adminRegistration)
            ->with('success', 'Application approved and login created.');
    }

    public function reject(
        ReviewAdminRegistrationRequest $request,
        AdminRegistrationRequest $adminRegistration,
        AdminRegistrationApprovalService $approvalService
    ): RedirectResponse {
        $approvalService->reject($adminRegistration, $request->user(), $request->validated('review_notes'));

        return redirect()
            ->route('admin-registrations.show', $adminRegistration)
            ->with('success', 'Application rejected.');
    }

    public function deactivate(
        ReviewAdminRegistrationRequest $request,
        AdminRegistrationRequest $adminRegistration,
        AdminRegistrationApprovalService $approvalService
    ): RedirectResponse {
        $approvalService->deactivate($adminRegistration, $request->user(), $request->validated('review_notes'));

        return redirect()
            ->route('admin-registrations.show', $adminRegistration)
            ->with('success', 'Application deactivated.');
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    private function entityTypes(): array
    {
        return [
            [
                'value' => AdminRegistrationRequest::TYPE_ORGANIZATION,
                'label' => 'Organization',
                'description' => 'NGOs, associations, companies, or groups that want to conduct exams.',
            ],
            [
                'value' => AdminRegistrationRequest::TYPE_SECONDARY_SCHOOL,
                'label' => 'Secondary School',
                'description' => 'Secondary schools that manage students, sessions, terms, classes, arms, subjects, and terminal exams.',
            ],
            [
                'value' => AdminRegistrationRequest::TYPE_PROFESSIONAL_SCHOOL,
                'label' => 'Professional School',
                'description' => 'Professional academies, bootcamps, and certification schools that manage programmes, courses, modules, and certificates.',
            ],
            [
                'value' => AdminRegistrationRequest::TYPE_CBT_CENTER,
                'label' => 'CBT Center',
                'description' => 'Facilities capable of delivering online or local CBT exams.',
            ],
        ];
    }

    private function notifyRegistrationReceived(AdminRegistrationRequest $registration): void
    {
        try {
            app(NotificationDispatcher::class)->dispatch(
                'admin_application_received',
                [
                    'name' => $registration->admin_name,
                    'email' => $registration->admin_email,
                    'phone' => $registration->phone,
                ],
                [
                    'admin_name' => $registration->admin_name,
                    'admin_email' => $registration->admin_email,
                    'application_name' => $registration->entity_name,
                    'submitted_by' => $registration->admin_name,
                    'reference' => 'AX-APP-'.$registration->id,
                    'portal_login_url' => route('login', absolute: true),
                    'password_reset_url' => route('password.request', absolute: true),
                ],
            );
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @return array<int, array{id: int, slug: string, name: string, label: string, description: string}>
     */
    private function registrationPlans(bool $includeInactive = false): array
    {
        return PricingPlan::query()
            ->when(! $includeInactive, fn ($query) => $query->where('is_active', true))
            ->ordered()
            ->get()
            ->map(function (PricingPlan $plan): array {
                $resource = PricingPlanResource::make($plan)->toArray(request());

                return [
                    'id' => $plan->id,
                    'slug' => $plan->slug,
                    'name' => $plan->name,
                    'label' => $resource['formatted_price'].' '.$resource['billing_label'],
                    'description' => $plan->description,
                ];
            })
            ->values()
            ->all();
    }
}

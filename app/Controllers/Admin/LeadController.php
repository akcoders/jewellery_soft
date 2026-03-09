<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\LeadFollowupModel;
use App\Models\LeadImageModel;
use App\Models\LeadModel;
use App\Models\LeadNoteModel;
use App\Models\LeadSourceModel;
use Config\Jewellery;

class LeadController extends BaseController
{
    private LeadModel $leadModel;
    private LeadSourceModel $sourceModel;
    private LeadImageModel $imageModel;
    private LeadNoteModel $noteModel;
    private LeadFollowupModel $followupModel;
    private Jewellery $jewelleryConfig;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->leadModel      = new LeadModel();
        $this->sourceModel    = new LeadSourceModel();
        $this->imageModel     = new LeadImageModel();
        $this->noteModel      = new LeadNoteModel();
        $this->followupModel  = new LeadFollowupModel();
        $this->jewelleryConfig = config(Jewellery::class);
    }

    public function index(): string
    {
        $leads = $this->leadModel
            ->select('leads.*, lead_sources.name as source_name')
            ->join('lead_sources', 'lead_sources.id = leads.source_id', 'left')
            ->orderBy('leads.id', 'DESC')
            ->findAll();

        return view('admin/leads/index', [
            'title' => 'Leads',
            'leads' => $leads,
        ]);
    }

    public function create(): string
    {
        return view('admin/leads/create', [
            'title'      => 'Add Lead',
            'sources'    => $this->sourceModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
            'leadStages' => $this->jewelleryConfig->leadStages,
        ]);
    }

    public function store()
    {
        $rules = [
            'name'             => 'required|min_length[2]|max_length[150]',
            'phone'            => 'required|min_length[8]|max_length[20]',
            'email'            => 'permit_empty|valid_email',
            'source_id'        => 'permit_empty|integer',
            'city'             => 'permit_empty|max_length[120]',
            'requirement_text' => 'permit_empty',
            'stage'            => 'required',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $stage = (string) $this->request->getPost('stage');
        if (! in_array($stage, $this->jewelleryConfig->leadStages, true)) {
            return redirect()->back()->withInput()->with('error', 'Invalid lead stage.');
        }

        $leadNo = 'LD' . date('ymdHis') . random_int(10, 99);

        $leadId = $this->leadModel->insert([
            'lead_no'          => $leadNo,
            'name'             => trim((string) $this->request->getPost('name')),
            'phone'            => trim((string) $this->request->getPost('phone')),
            'email'            => trim((string) $this->request->getPost('email')),
            'source_id'        => $this->nullableInt($this->request->getPost('source_id')),
            'city'             => trim((string) $this->request->getPost('city')),
            'requirement_text' => trim((string) $this->request->getPost('requirement_text')),
            'stage'            => $stage,
            'status'           => 'Open',
            'created_by'       => (int) session('admin_id'),
        ], true);

        $this->storeLeadImages((int) $leadId);

        return redirect()->to(site_url('admin/leads/' . $leadId))->with('success', 'Lead created successfully.');
    }

    public function show(int $id): string
    {
        $lead = $this->leadModel
            ->select('leads.*, lead_sources.name as source_name')
            ->join('lead_sources', 'lead_sources.id = leads.source_id', 'left')
            ->find($id);

        if (! $lead) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Lead not found.');
        }

        return view('admin/leads/show', [
            'title'      => 'Lead Details',
            'lead'       => $lead,
            'leadStages' => $this->jewelleryConfig->leadStages,
            'images'     => $this->imageModel->where('lead_id', $id)->orderBy('id', 'DESC')->findAll(),
            'notes'      => $this->noteModel->where('lead_id', $id)->orderBy('id', 'DESC')->findAll(),
            'followups'  => $this->followupModel->where('lead_id', $id)->orderBy('followup_at', 'ASC')->findAll(),
        ]);
    }

    public function addNote(int $id)
    {
        $lead = $this->leadModel->find($id);
        if (! $lead) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Lead not found.');
        }

        $rules = ['note' => 'required|min_length[2]'];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $this->noteModel->insert([
            'lead_id'    => $id,
            'note'       => trim((string) $this->request->getPost('note')),
            'created_by' => (int) session('admin_id'),
        ]);

        return redirect()->to(site_url('admin/leads/' . $id))->with('success', 'Note saved.');
    }

    public function addFollowup(int $id)
    {
        $lead = $this->leadModel->find($id);
        if (! $lead) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Lead not found.');
        }

        $rules = [
            'followup_at' => 'required',
            'reminder_at' => 'permit_empty',
            'notes'       => 'permit_empty',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $this->followupModel->insert([
            'lead_id'      => $id,
            'followup_at'  => $this->normalizeDateTime((string) $this->request->getPost('followup_at')),
            'reminder_at'  => $this->normalizeDateTime((string) $this->request->getPost('reminder_at')),
            'status'       => 'Pending',
            'notes'        => trim((string) $this->request->getPost('notes')),
            'created_by'   => (int) session('admin_id'),
        ]);

        return redirect()->to(site_url('admin/leads/' . $id))->with('success', 'Follow-up scheduled.');
    }

    public function updateStage(int $id)
    {
        $lead = $this->leadModel->find($id);
        if (! $lead) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Lead not found.');
        }

        $stage = (string) $this->request->getPost('stage');
        if (! in_array($stage, $this->jewelleryConfig->leadStages, true)) {
            return redirect()->back()->with('error', 'Invalid lead stage.');
        }

        $this->leadModel->update($id, ['stage' => $stage]);

        return redirect()->to(site_url('admin/leads/' . $id))->with('success', 'Lead stage updated.');
    }

    public function addImage(int $id)
    {
        $lead = $this->leadModel->find($id);
        if (! $lead) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Lead not found.');
        }

        $this->storeLeadImages($id);

        return redirect()->to(site_url('admin/leads/' . $id))->with('success', 'Images uploaded.');
    }

    private function storeLeadImages(int $leadId): void
    {
        $files = $this->request->getFileMultiple('lead_images');
        if (! is_array($files)) {
            return;
        }

        $uploadDir = FCPATH . 'uploads/leads';
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        foreach ($files as $file) {
            if (! $file || ! $file->isValid() || $file->hasMoved()) {
                continue;
            }

            $newName = $file->getRandomName();
            $file->move($uploadDir, $newName);

            $this->imageModel->insert([
                'lead_id'    => $leadId,
                'file_name'  => $file->getClientName(),
                'file_path'  => 'uploads/leads/' . $newName,
            ]);
        }
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizeDateTime(string $dateTime): ?string
    {
        if ($dateTime === '') {
            return null;
        }

        return date('Y-m-d H:i:s', strtotime($dateTime));
    }

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }
}


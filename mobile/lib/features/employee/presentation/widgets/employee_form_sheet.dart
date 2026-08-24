import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/employee/domain/employee.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

Future<EmployeeFormValue?> showEmployeeFormSheet(
  BuildContext context, {
  EmployeeDetail? existing,
}) => showModalBottomSheet<EmployeeFormValue>(
  context: context,
  isScrollControlled: true,
  useSafeArea: true,
  builder: (context) => _EmployeeFormSheet(existing: existing),
);

class _EmployeeFormSheet extends StatefulWidget {
  const _EmployeeFormSheet({this.existing});

  final EmployeeDetail? existing;

  @override
  State<_EmployeeFormSheet> createState() => _EmployeeFormSheetState();
}

class _EmployeeFormSheetState extends State<_EmployeeFormSheet> {
  late final Map<String, TextEditingController> _controllers;
  late String _gender;
  late bool _active;
  late DateTime? _birthDate;
  late DateTime? _workStartDate;
  late DateTime? _dutyStartDate;
  String? _error;

  bool get _editing => widget.existing != null;

  @override
  void initState() {
    super.initState();
    final value = widget.existing == null
        ? const EmployeeFormValue(name: '', active: true)
        : EmployeeFormValue.fromDetail(widget.existing!);
    _controllers = {
      'name': TextEditingController(text: value.name),
      'nip': TextEditingController(text: value.nip),
      'nuptk': TextEditingController(text: value.nuptk),
      'nik': TextEditingController(text: value.nik),
      'birthPlace': TextEditingController(text: value.birthPlace),
      'address': TextEditingController(text: value.address),
      'email': TextEditingController(text: value.email),
      'phone': TextEditingController(text: value.phone),
      'employmentStatus': TextEditingController(text: value.employmentStatus),
      'rank': TextEditingController(text: value.rank),
      'employeeType': TextEditingController(text: value.employeeType),
      'primaryPosition': TextEditingController(text: value.primaryPosition),
      'salarySource': TextEditingController(text: value.salarySource),
      'lastEducation': TextEditingController(text: value.lastEducation),
      'educationMajor': TextEditingController(text: value.educationMajor),
      'graduationYear': TextEditingController(
        text: value.graduationYear?.toString(),
      ),
      'notes': TextEditingController(text: value.notes),
    };
    _gender = value.gender ?? '';
    _active = value.active;
    _birthDate = value.birthDate;
    _workStartDate = value.workStartDate;
    _dutyStartDate = value.dutyStartDate;
  }

  @override
  void dispose() {
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SizedBox(
      height: MediaQuery.sizeOf(context).height * 0.92,
      child: Column(
        children: [
          const SizedBox(height: 10),
          Container(
            width: 42,
            height: 4,
            decoration: BoxDecoration(
              color: NusaColors.outline,
              borderRadius: BorderRadius.circular(4),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 8, 10),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    _editing ? 'Ubah Data Pegawai' : 'Tambah Pegawai',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                IconButton(
                  key: const Key('close-employee-form'),
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView(
              key: const Key('employee-form-scroll'),
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
              children: [
                const _FormSectionTitle(
                  icon: Icons.badge_outlined,
                  title: 'Identitas Utama',
                ),
                _textField(
                  key: const Key('employee-form-name'),
                  controller: _controllers['name']!,
                  label: 'Nama lengkap',
                  icon: Icons.person_outline_rounded,
                  maxLength: 255,
                  textCapitalization: TextCapitalization.words,
                ),
                const SizedBox(height: 11),
                _textField(
                  key: const Key('employee-form-nip'),
                  controller: _controllers['nip']!,
                  label: 'NIP (opsional)',
                  icon: Icons.numbers_rounded,
                  maxLength: 50,
                ),
                const SizedBox(height: 11),
                _textField(
                  key: const Key('employee-form-nuptk'),
                  controller: _controllers['nuptk']!,
                  label: 'NUPTK',
                  icon: Icons.fingerprint_rounded,
                  maxLength: 50,
                ),
                const SizedBox(height: 11),
                _textField(
                  key: const Key('employee-form-nik'),
                  controller: _controllers['nik']!,
                  label: 'NIK',
                  icon: Icons.credit_card_rounded,
                  maxLength: 50,
                ),
                const SizedBox(height: 11),
                NusaDropdownField<String>(
                  fieldKey: const Key('employee-form-gender'),
                  value: _gender,
                  decoration: const InputDecoration(
                    labelText: 'Jenis kelamin',
                    prefixIcon: Icon(Icons.wc_rounded),
                  ),
                  options: const [
                    NusaDropdownOption(value: '', label: 'Belum dipilih'),
                    NusaDropdownOption(value: 'L', label: 'Laki-laki'),
                    NusaDropdownOption(value: 'P', label: 'Perempuan'),
                  ],
                  onChanged: (value) => setState(() => _gender = value ?? ''),
                ),
                const SizedBox(height: 20),
                const _FormSectionTitle(
                  icon: Icons.contact_phone_outlined,
                  title: 'Pribadi & Kontak',
                ),
                _textField(
                  key: const Key('employee-form-birth-place'),
                  controller: _controllers['birthPlace']!,
                  label: 'Tempat lahir',
                  icon: Icons.location_city_outlined,
                  maxLength: 100,
                  textCapitalization: TextCapitalization.words,
                ),
                const SizedBox(height: 11),
                _EmployeeDateField(
                  fieldKey: const Key('employee-form-birth-date'),
                  label: 'Tanggal lahir',
                  value: _birthDate,
                  onTap: () => _pickDate(_EmployeeDateType.birth),
                  onClear: _birthDate == null
                      ? null
                      : () => setState(() => _birthDate = null),
                ),
                const SizedBox(height: 11),
                _textField(
                  key: const Key('employee-form-email'),
                  controller: _controllers['email']!,
                  label: 'Email',
                  icon: Icons.email_outlined,
                  keyboardType: TextInputType.emailAddress,
                  maxLength: 255,
                ),
                const SizedBox(height: 11),
                _textField(
                  key: const Key('employee-form-phone'),
                  controller: _controllers['phone']!,
                  label: 'Nomor HP',
                  icon: Icons.phone_outlined,
                  keyboardType: TextInputType.phone,
                  maxLength: 30,
                ),
                const SizedBox(height: 11),
                _textField(
                  key: const Key('employee-form-address'),
                  controller: _controllers['address']!,
                  label: 'Alamat',
                  icon: Icons.home_outlined,
                  minLines: 2,
                  maxLines: 4,
                  textCapitalization: TextCapitalization.sentences,
                ),
                const SizedBox(height: 20),
                const _FormSectionTitle(
                  icon: Icons.work_outline_rounded,
                  title: 'Kepegawaian',
                ),
                _textField(
                  key: const Key('employee-form-type'),
                  controller: _controllers['employeeType']!,
                  label: 'Jenis pegawai',
                  hint: 'Contoh: Guru atau Tenaga Kependidikan',
                  icon: Icons.groups_outlined,
                  maxLength: 100,
                  textCapitalization: TextCapitalization.words,
                ),
                const SizedBox(height: 11),
                _textField(
                  key: const Key('employee-form-employment-status'),
                  controller: _controllers['employmentStatus']!,
                  label: 'Status kepegawaian',
                  hint: 'Contoh: PNS, PPPK, atau Honor',
                  icon: Icons.verified_user_outlined,
                  maxLength: 100,
                ),
                const SizedBox(height: 11),
                _textField(
                  key: const Key('employee-form-position'),
                  controller: _controllers['primaryPosition']!,
                  label: 'Jabatan utama',
                  icon: Icons.account_tree_outlined,
                  maxLength: 100,
                  textCapitalization: TextCapitalization.words,
                ),
                const SizedBox(height: 11),
                _textField(
                  key: const Key('employee-form-rank'),
                  controller: _controllers['rank']!,
                  label: 'Golongan',
                  icon: Icons.military_tech_outlined,
                  maxLength: 50,
                ),
                const SizedBox(height: 11),
                _EmployeeDateField(
                  fieldKey: const Key('employee-form-work-start-date'),
                  label: 'Tanggal mulai kerja',
                  value: _workStartDate,
                  onTap: () => _pickDate(_EmployeeDateType.work),
                  onClear: _workStartDate == null
                      ? null
                      : () => setState(() => _workStartDate = null),
                ),
                const SizedBox(height: 11),
                _EmployeeDateField(
                  fieldKey: const Key('employee-form-duty-start-date'),
                  label: 'Tanggal mulai bertugas',
                  value: _dutyStartDate,
                  onTap: () => _pickDate(_EmployeeDateType.duty),
                  onClear: _dutyStartDate == null
                      ? null
                      : () => setState(() => _dutyStartDate = null),
                ),
                const SizedBox(height: 11),
                _textField(
                  key: const Key('employee-form-salary-source'),
                  controller: _controllers['salarySource']!,
                  label: 'Sumber gaji',
                  icon: Icons.payments_outlined,
                  maxLength: 100,
                ),
                const SizedBox(height: 20),
                const _FormSectionTitle(
                  icon: Icons.school_outlined,
                  title: 'Pendidikan & Catatan',
                ),
                _textField(
                  key: const Key('employee-form-last-education'),
                  controller: _controllers['lastEducation']!,
                  label: 'Pendidikan terakhir',
                  icon: Icons.workspace_premium_outlined,
                  maxLength: 100,
                ),
                const SizedBox(height: 11),
                _textField(
                  key: const Key('employee-form-education-major'),
                  controller: _controllers['educationMajor']!,
                  label: 'Jurusan pendidikan',
                  icon: Icons.menu_book_outlined,
                  maxLength: 150,
                  textCapitalization: TextCapitalization.words,
                ),
                const SizedBox(height: 11),
                _textField(
                  key: const Key('employee-form-graduation-year'),
                  controller: _controllers['graduationYear']!,
                  label: 'Tahun lulus',
                  icon: Icons.event_outlined,
                  keyboardType: TextInputType.number,
                  maxLength: 4,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                ),
                const SizedBox(height: 11),
                _textField(
                  key: const Key('employee-form-notes'),
                  controller: _controllers['notes']!,
                  label: 'Keterangan',
                  icon: Icons.notes_rounded,
                  minLines: 2,
                  maxLines: 4,
                  textCapitalization: TextCapitalization.sentences,
                ),
                const SizedBox(height: 8),
                SwitchListTile.adaptive(
                  key: const Key('employee-form-active'),
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Pegawai aktif'),
                  subtitle: const Text(
                    'Pegawai aktif dapat dipilih pada penugasan sekolah.',
                  ),
                  value: _active,
                  onChanged: (value) => setState(() => _active = value),
                ),
                if (widget.existing?.account.available == true) ...[
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: NusaColors.accent.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(13),
                    ),
                    child: const Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(
                          Icons.info_outline_rounded,
                          color: NusaColors.textPrimary,
                        ),
                        SizedBox(width: 9),
                        Expanded(
                          child: Text(
                            'Jika NIP diubah, username akun login pegawai '
                            'akan ikut disesuaikan.',
                            style: TextStyle(fontSize: 11.5),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
                if (_error != null) ...[
                  const SizedBox(height: 10),
                  Text(
                    _error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                      fontSize: 12,
                    ),
                  ),
                ],
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
            child: SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                key: const Key('save-employee'),
                onPressed: _submit,
                icon: const Icon(Icons.save_outlined),
                label: Text(_editing ? 'Simpan Perubahan' : 'Simpan Pegawai'),
              ),
            ),
          ),
        ],
      ),
    ),
  );

  TextFormField _textField({
    required Key key,
    required TextEditingController controller,
    required String label,
    required IconData icon,
    String? hint,
    int? maxLength,
    int? minLines,
    int maxLines = 1,
    TextInputType? keyboardType,
    TextCapitalization textCapitalization = TextCapitalization.none,
    List<TextInputFormatter>? inputFormatters,
  }) => TextFormField(
    key: key,
    controller: controller,
    maxLength: maxLength,
    minLines: minLines,
    maxLines: maxLines,
    keyboardType: keyboardType,
    textCapitalization: textCapitalization,
    inputFormatters: inputFormatters,
    decoration: InputDecoration(
      labelText: label,
      hintText: hint,
      prefixIcon: Icon(icon),
      alignLabelWithHint: (minLines ?? 1) > 1,
      counterText: '',
    ),
  );

  Future<void> _pickDate(_EmployeeDateType type) async {
    final now = DateTime.now();
    final current = switch (type) {
      _EmployeeDateType.birth => _birthDate,
      _EmployeeDateType.work => _workStartDate,
      _EmployeeDateType.duty => _dutyStartDate,
    };
    final value = await showDatePicker(
      context: context,
      initialDate: current ?? now,
      firstDate: DateTime(1900),
      lastDate: DateTime(2100, 12, 31),
    );
    if (value == null || !mounted) return;
    setState(() {
      switch (type) {
        case _EmployeeDateType.birth:
          _birthDate = value;
        case _EmployeeDateType.work:
          _workStartDate = value;
        case _EmployeeDateType.duty:
          _dutyStartDate = value;
      }
    });
  }

  void _submit() {
    final name = _controllers['name']!.text.trim();
    final email = _text('email');
    final graduationText = _controllers['graduationYear']!.text.trim();
    final graduationYear = graduationText.isEmpty
        ? null
        : int.tryParse(graduationText);

    if (name.isEmpty) {
      setState(() => _error = 'Nama lengkap wajib diisi.');
      return;
    }
    if (email != null &&
        !RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email)) {
      setState(() => _error = 'Format email belum valid.');
      return;
    }
    if (graduationText.isNotEmpty &&
        (graduationYear == null ||
            graduationYear < 1900 ||
            graduationYear > 2100)) {
      setState(() => _error = 'Tahun lulus harus berada antara 1900–2100.');
      return;
    }

    Navigator.pop(
      context,
      EmployeeFormValue(
        name: name,
        nip: _text('nip'),
        nuptk: _text('nuptk'),
        nik: _text('nik'),
        gender: _gender.isEmpty ? null : _gender,
        birthPlace: _text('birthPlace'),
        birthDate: _birthDate,
        address: _text('address'),
        email: email,
        phone: _text('phone'),
        employmentStatus: _text('employmentStatus'),
        rank: _text('rank'),
        workStartDate: _workStartDate,
        dutyStartDate: _dutyStartDate,
        employeeType: _text('employeeType'),
        primaryPosition: _text('primaryPosition'),
        salarySource: _text('salarySource'),
        lastEducation: _text('lastEducation'),
        educationMajor: _text('educationMajor'),
        graduationYear: graduationYear,
        notes: _text('notes'),
        active: _active,
      ),
    );
  }

  String? _text(String key) {
    final value = _controllers[key]!.text.trim();
    return value.isEmpty ? null : value;
  }
}

enum _EmployeeDateType { birth, work, duty }

class _FormSectionTitle extends StatelessWidget {
  const _FormSectionTitle({required this.icon, required this.title});

  final IconData icon;
  final String title;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 11),
    child: Row(
      children: [
        Icon(icon, size: 19, color: NusaColors.primary),
        const SizedBox(width: 8),
        Text(
          title,
          style: const TextStyle(
            color: NusaColors.textPrimary,
            fontSize: 14,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    ),
  );
}

class _EmployeeDateField extends StatelessWidget {
  const _EmployeeDateField({
    required this.fieldKey,
    required this.label,
    required this.value,
    required this.onTap,
    this.onClear,
  });

  final Key fieldKey;
  final String label;
  final DateTime? value;
  final VoidCallback onTap;
  final VoidCallback? onClear;

  @override
  Widget build(BuildContext context) => Material(
    key: fieldKey,
    color: Colors.transparent,
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: label,
          prefixIcon: const Icon(Icons.calendar_today_outlined),
          suffixIcon: onClear == null
              ? const Icon(Icons.chevron_right_rounded)
              : IconButton(
                  tooltip: 'Hapus tanggal',
                  onPressed: onClear,
                  icon: const Icon(Icons.close_rounded),
                ),
        ),
        child: Text(
          value == null ? 'Belum dipilih' : _formatDate(value!),
          style: TextStyle(
            color: value == null
                ? NusaColors.textSecondary
                : NusaColors.textPrimary,
          ),
        ),
      ),
    ),
  );
}

String _formatDate(DateTime value) {
  const months = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
  ];
  return '${value.day} ${months[value.month - 1]} ${value.year}';
}

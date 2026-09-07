import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/auth/domain/pengguna.dart';
import 'package:nusa/features/profile/application/my_profile_controller.dart';
import 'package:nusa/features/profile/data/my_profile_photo_picker.dart';
import 'package:nusa/features/profile/domain/my_profile.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class ProfilePage extends ConsumerStatefulWidget {
  const ProfilePage({
    required this.user,
    required this.onRefreshRelatedData,
    required this.onChangePassword,
    required this.onLogout,
    required this.isLoggingOut,
    super.key,
  });

  final Pengguna user;
  final Future<void> Function() onRefreshRelatedData;
  final VoidCallback onChangePassword;
  final Future<void> Function() onLogout;
  final bool isLoggingOut;

  @override
  ConsumerState<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends ConsumerState<ProfilePage> {
  bool _editing = false;
  bool _uploadingPhoto = false;

  @override
  Widget build(BuildContext context) {
    final profile = ref.watch(myProfileControllerProvider);

    return profile.when(
      skipLoadingOnRefresh: true,
      loading: () => _ProfileLoading(user: widget.user),
      error: (error, stackTrace) =>
          _ProfileError(message: _errorMessage(error), onRetry: _refresh),
      data: (data) => _editing
          ? _ProfileEditForm(
              key: ValueKey('${data.kind.name}-${data.username}'),
              profile: data,
              onCancel: () => setState(() => _editing = false),
              onSave: _save,
            )
          : _ProfileOverview(
              profile: data,
              uploadingPhoto: _uploadingPhoto,
              loggingOut: widget.isLoggingOut,
              onRefresh: _refresh,
              onEdit: () => setState(() => _editing = true),
              onChangePhoto: data.canChangePhoto ? _changePhoto : null,
              onChangePassword: widget.onChangePassword,
              onLogout: widget.onLogout,
            ),
    );
  }

  Future<void> _refresh() async {
    await Future.wait([
      ref.read(myProfileControllerProvider.notifier).refresh(),
      widget.onRefreshRelatedData(),
    ]);
  }

  Future<void> _save(Map<String, dynamic> payload) async {
    final message = await ref
        .read(myProfileControllerProvider.notifier)
        .saveProfile(payload);
    await widget.onRefreshRelatedData();
    if (!mounted) return;

    setState(() => _editing = false);
    _showMessage(message);
  }

  Future<void> _changePhoto() async {
    final source = await showModalBottomSheet<MyProfilePhotoSource>(
      context: context,
      useSafeArea: true,
      builder: (context) => const _PhotoSourceSheet(),
    );
    if (source == null || !mounted) return;

    try {
      final file = await ref.read(myProfilePhotoPickerProvider).pick(source);
      if (file == null || !mounted) return;
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Gunakan foto ini?'),
          content: ClipRRect(
            borderRadius: BorderRadius.circular(16),
            child: Image.memory(
              file.bytes,
              width: 180,
              height: 220,
              fit: BoxFit.cover,
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Pilih Ulang'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Unggah'),
            ),
          ],
        ),
      );
      if (confirmed != true || !mounted) return;

      setState(() => _uploadingPhoto = true);
      await ref.read(myProfileControllerProvider.notifier).updatePhoto(file);
      await widget.onRefreshRelatedData();
      if (mounted) _showMessage('Foto profil berhasil diperbarui.');
    } catch (error) {
      if (mounted) _showMessage(_errorMessage(error), error: true);
    } finally {
      if (mounted) setState(() => _uploadingPhoto = false);
    }
  }

  void _showMessage(String message, {bool error = false}) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(message),
          backgroundColor: error ? Theme.of(context).colorScheme.error : null,
        ),
      );
  }
}

class _ProfileOverview extends StatelessWidget {
  const _ProfileOverview({
    required this.profile,
    required this.uploadingPhoto,
    required this.loggingOut,
    required this.onRefresh,
    required this.onEdit,
    required this.onChangePassword,
    required this.onLogout,
    this.onChangePhoto,
  });

  final MyProfile profile;
  final bool uploadingPhoto;
  final bool loggingOut;
  final Future<void> Function() onRefresh;
  final VoidCallback onEdit;
  final VoidCallback? onChangePhoto;
  final VoidCallback onChangePassword;
  final Future<void> Function() onLogout;

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        key: const PageStorageKey<String>('profile-scroll'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 28),
        children: [
          _ProfileHeader(
            profile: profile,
            uploadingPhoto: uploadingPhoto,
            onChangePhoto: onChangePhoto,
          ),
          const SizedBox(height: 12),
          _AccountCard(profile: profile),
          const SizedBox(height: 12),
          _ProfileDetailsCard(profile: profile),
          if (profile.kind == MyProfileKind.student &&
              profile.school != null) ...[
            const SizedBox(height: 12),
            _SchoolCard(school: profile.school!),
          ],
          if (profile.kind == MyProfileKind.parent) ...[
            const SizedBox(height: 12),
            _ChildrenCard(children: profile.children),
          ],
          const SizedBox(height: 16),
          FilledButton.icon(
            key: const Key('profile-edit-button'),
            onPressed: uploadingPhoto ? null : onEdit,
            icon: const Icon(Icons.edit_outlined),
            label: const Text('Edit Profil'),
          ),
          const SizedBox(height: 9),
          OutlinedButton.icon(
            onPressed: uploadingPhoto ? null : onChangePassword,
            icon: const Icon(Icons.lock_outline_rounded),
            label: const Text('Ganti Kata Sandi'),
            style: OutlinedButton.styleFrom(
              minimumSize: const Size.fromHeight(50),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
              ),
            ),
          ),
          const SizedBox(height: 9),
          OutlinedButton.icon(
            onPressed: loggingOut || uploadingPhoto ? null : onLogout,
            icon: loggingOut
                ? const SizedBox.square(
                    dimension: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.logout_rounded),
            label: const Text('Keluar dari NUSA'),
            style: OutlinedButton.styleFrom(
              foregroundColor: Theme.of(context).colorScheme.error,
              minimumSize: const Size.fromHeight(50),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ProfileHeader extends StatelessWidget {
  const _ProfileHeader({
    required this.profile,
    required this.uploadingPhoto,
    this.onChangePhoto,
  });

  final MyProfile profile;
  final bool uploadingPhoto;
  final VoidCallback? onChangePhoto;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(18, 22, 18, 20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [NusaColors.primary, NusaColors.primaryDark],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: NusaColors.primary.withValues(alpha: 0.18),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        children: [
          Stack(
            clipBehavior: Clip.none,
            children: [
              _Avatar(name: profile.name, photoUrl: profile.photoUrl, size: 88),
              if (onChangePhoto != null)
                Positioned(
                  right: -5,
                  bottom: -3,
                  child: Material(
                    color: NusaColors.accent,
                    shape: const CircleBorder(),
                    child: InkWell(
                      key: const Key('profile-photo-button'),
                      customBorder: const CircleBorder(),
                      onTap: uploadingPhoto ? null : onChangePhoto,
                      child: SizedBox.square(
                        dimension: 34,
                        child: uploadingPhoto
                            ? const Padding(
                                padding: EdgeInsets.all(9),
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: NusaColors.primaryDark,
                                ),
                              )
                            : const Icon(
                                Icons.photo_camera_outlined,
                                size: 19,
                                color: NusaColors.primaryDark,
                              ),
                      ),
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 13),
          Text(
            profile.name,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.titleLarge
                ?.copyWith(color: Colors.white, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 5),
          const Text(
            'Profil Pengguna NUSA',
            style: TextStyle(
              color: NusaColors.accent,
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _AccountCard extends StatelessWidget {
  const _AccountCard({required this.profile});

  final MyProfile profile;

  @override
  Widget build(BuildContext context) {
    return _SectionCard(
      title: 'Informasi Akun',
      icon: Icons.account_circle_outlined,
      children: [
        _InfoRow(label: 'Username', value: profile.username),
        _InfoRow(
          label: 'Terakhir masuk',
          value: _dateTime(profile.lastLoginAt),
        ),
      ],
    );
  }
}

class _ProfileDetailsCard extends StatelessWidget {
  const _ProfileDetailsCard({required this.profile});

  final MyProfile profile;

  @override
  Widget build(BuildContext context) {
    final details = profile.details;
    final rows = <Widget>[
      if (profile.kind == MyProfileKind.employee) ...[
        _InfoRow(label: 'NIP', value: details.nip),
        _InfoRow(label: 'NUPTK', value: details.nuptk),
        _InfoRow(label: 'NIK', value: details.nik),
        _InfoRow(label: 'Jabatan', value: details.position),
        _InfoRow(label: 'Jenis pegawai', value: details.employeeType),
        _InfoRow(label: 'Jenis kelamin', value: _gender(details.gender)),
        _InfoRow(
          label: 'Tempat, tanggal lahir',
          value: _birth(details.birthPlace, details.birthDate),
        ),
        _InfoRow(label: 'Email', value: details.email),
        _InfoRow(label: 'Nomor HP', value: details.phone),
        _InfoRow(label: 'Alamat', value: details.address),
        _InfoRow(label: 'Pendidikan', value: details.lastEducation),
        _InfoRow(label: 'Jurusan', value: details.educationMajor),
        _InfoRow(
          label: 'Tahun lulus',
          value: details.graduationYear?.toString(),
        ),
      ] else if (profile.kind == MyProfileKind.student) ...[
        _InfoRow(label: 'NIS', value: details.nis),
        _InfoRow(label: 'NISN', value: details.nisn),
        _InfoRow(label: 'Jenis kelamin', value: _gender(details.gender)),
        _InfoRow(
          label: 'Tempat, tanggal lahir',
          value: _birth(details.birthPlace, details.birthDate),
        ),
        _InfoRow(label: 'Agama', value: details.religion),
        _InfoRow(label: 'Alamat', value: details.address),
      ] else if (profile.kind == MyProfileKind.parent) ...[
        _InfoRow(label: 'Nama lengkap', value: details.fullName),
        _InfoRow(label: 'Nomor WhatsApp', value: details.whatsAppNumber),
      ] else ...[
        _InfoRow(label: 'Nama tampilan', value: details.fullName),
      ],
    ];

    return _SectionCard(
      title: profile.kind == MyProfileKind.student
          ? 'Identitas Siswa'
          : 'Data Pribadi',
      icon: Icons.badge_outlined,
      children: rows,
    );
  }
}

class _SchoolCard extends StatelessWidget {
  const _SchoolCard({required this.school});

  final MyProfileSchool school;

  @override
  Widget build(BuildContext context) {
    return _SectionCard(
      title: 'Data Sekolah',
      icon: Icons.school_outlined,
      children: [
        _InfoRow(label: 'Kelas', value: school.className),
        _InfoRow(
          label: 'Nomor absen',
          value: school.attendanceNumber?.toString(),
        ),
        _InfoRow(label: 'Tahun pelajaran', value: school.academicYear),
        _InfoRow(label: 'Wali kelas', value: school.homeroomTeacher),
      ],
    );
  }
}

class _ChildrenCard extends StatelessWidget {
  const _ChildrenCard({required this.children});

  final List<MyProfileChild> children;

  @override
  Widget build(BuildContext context) {
    return _SectionCard(
      title: 'Anak Terhubung',
      icon: Icons.family_restroom_outlined,
      children: children.isEmpty
          ? const [
              Padding(
                padding: EdgeInsets.symmetric(vertical: 8),
                child: Text(
                  'Belum ada siswa yang terhubung dengan akun ini.',
                  style: TextStyle(color: NusaColors.textSecondary),
                ),
              ),
            ]
          : [
              for (final child in children)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _Avatar(
                        name: child.name,
                        photoUrl: child.photoUrl,
                        size: 48,
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              child.name,
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            const SizedBox(height: 3),
                            Text(
                              [
                                if (child.nisn != null) 'NISN ${child.nisn}',
                                if (child.school?.className != null)
                                  child.school!.className!,
                              ].join(' · '),
                              style: const TextStyle(
                                color: NusaColors.textSecondary,
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
            ],
    );
  }
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({
    required this.title,
    required this.icon,
    required this.children,
  });

  final String title;
  final IconData icon;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                    color: NusaColors.surfaceBlue,
                    borderRadius: BorderRadius.circular(11),
                  ),
                  child: Icon(icon, color: NusaColors.primary, size: 20),
                ),
                const SizedBox(width: 10),
                Text(
                  title,
                  style: const TextStyle(
                    color: NusaColors.textPrimary,
                    fontWeight: FontWeight.w800,
                    fontSize: 16,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 9),
            ...children,
          ],
        ),
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, this.value});

  final String label;
  final String? value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 7),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 112,
            child: Text(
              label,
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 13,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value?.trim().isNotEmpty == true ? value! : '-',
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
          ),
        ],
      ),
    );
  }
}

class _Avatar extends StatelessWidget {
  const _Avatar({required this.name, required this.size, this.photoUrl});

  final String name;
  final String? photoUrl;
  final double size;

  @override
  Widget build(BuildContext context) {
    final fallback = Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: NusaColors.primaryLight,
        shape: BoxShape.circle,
        border: Border.all(color: Colors.white, width: 3),
      ),
      child: Text(
        _initials(name),
        style: TextStyle(
          color: Colors.white,
          fontSize: size * .3,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
    if (photoUrl == null || photoUrl!.trim().isEmpty) return fallback;

    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        border: Border.all(color: Colors.white, width: 3),
      ),
      child: ClipOval(
        child: Image.network(
          photoUrl!,
          fit: BoxFit.cover,
          errorBuilder: (context, error, stackTrace) => fallback,
        ),
      ),
    );
  }
}

class _ProfileEditForm extends StatefulWidget {
  const _ProfileEditForm({
    required this.profile,
    required this.onCancel,
    required this.onSave,
    super.key,
  });

  final MyProfile profile;
  final VoidCallback onCancel;
  final Future<void> Function(Map<String, dynamic>) onSave;

  @override
  State<_ProfileEditForm> createState() => _ProfileEditFormState();
}

class _ProfileEditFormState extends State<_ProfileEditForm> {
  final _formKey = GlobalKey<FormState>();
  final Map<String, TextEditingController> _fields = {};
  Map<String, List<String>> _serverErrors = const {};
  String? _error;
  String? _gender;
  bool _saving = false;

  MyProfileDetails get _details => widget.profile.details;

  @override
  void initState() {
    super.initState();
    _gender = _details.gender;
    _put('nama_lengkap', _details.fullName ?? widget.profile.name);
    _put('nuptk', _details.nuptk);
    _put('nik', _details.nik);
    _put('tempat_lahir', _details.birthPlace);
    _put('tanggal_lahir', _details.birthDate);
    _put('alamat', _details.address);
    _put('email', _details.email);
    _put('no_hp', _details.phone);
    _put('pendidikan_terakhir', _details.lastEducation);
    _put('jurusan_pendidikan', _details.educationMajor);
    _put('tahun_lulus', _details.graduationYear?.toString());
    _put('keterangan', _details.notes);
    _put('nomor_wa', _details.whatsAppNumber);
  }

  void _put(String key, String? value) {
    _fields[key] = TextEditingController(text: value ?? '');
  }

  @override
  void dispose() {
    for (final controller in _fields.values) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Form(
      key: _formKey,
      child: ListView(
        key: const PageStorageKey<String>('profile-edit-scroll'),
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          Row(
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: NusaColors.surfaceBlue,
                  borderRadius: BorderRadius.circular(13),
                ),
                child: const Icon(
                  Icons.manage_accounts_outlined,
                  color: NusaColors.primary,
                ),
              ),
              const SizedBox(width: 11),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Edit Profil',
                      style: TextStyle(
                        color: NusaColors.textPrimary,
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    Text(
                      'Perbarui data yang memang menjadi kewenangan Anda.',
                      style: TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (_error != null) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.errorContainer,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Text(_error!),
            ),
          ],
          const SizedBox(height: 18),
          if (widget.profile.kind == MyProfileKind.employee)
            ..._employeeFields()
          else if (widget.profile.kind == MyProfileKind.parent)
            ..._parentFields()
          else if (widget.profile.kind == MyProfileKind.student)
            ..._studentFields()
          else
            ..._accountFields(),
          const SizedBox(height: 20),
          FilledButton.icon(
            key: const Key('profile-save-button'),
            onPressed: _saving ? null : _submit,
            icon: _saving
                ? const SizedBox.square(
                    dimension: 18,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Icon(Icons.save_outlined),
            label: Text(_saving ? 'Menyimpan...' : 'Simpan Profil'),
          ),
          const SizedBox(height: 9),
          TextButton(
            onPressed: _saving ? null : widget.onCancel,
            child: const Text('Batal'),
          ),
        ],
      ),
    );
  }

  List<Widget> _employeeFields() => [
    _readOnly('NIP', _details.nip, Icons.badge_outlined),
    const SizedBox(height: 10),
    _field(
      'nama_lengkap',
      'Nama lengkap',
      Icons.person_outline_rounded,
      required: true,
    ),
    const SizedBox(height: 10),
    _field('nuptk', 'NUPTK', Icons.numbers_outlined),
    const SizedBox(height: 10),
    _field('nik', 'NIK', Icons.credit_card_outlined),
    const SizedBox(height: 10),
    NusaDropdownField<String>(
      fieldKey: const Key('profile-gender'),
      value: _gender ?? '',
      options: const [
        NusaDropdownOption(value: '', label: 'Belum ditentukan'),
        NusaDropdownOption(value: 'L', label: 'Laki-laki'),
        NusaDropdownOption(value: 'P', label: 'Perempuan'),
      ],
      decoration: InputDecoration(
        labelText: 'Jenis kelamin',
        prefixIcon: const Icon(Icons.wc_outlined),
        errorText: _fieldError('jenis_kelamin'),
      ),
      enabled: !_saving,
      onChanged: (value) => setState(() => _gender = value),
    ),
    const SizedBox(height: 10),
    _field('tempat_lahir', 'Tempat lahir', Icons.location_city_outlined),
    const SizedBox(height: 10),
    _dateField(),
    const SizedBox(height: 10),
    _field(
      'email',
      'Email',
      Icons.email_outlined,
      keyboardType: TextInputType.emailAddress,
    ),
    const SizedBox(height: 10),
    _field(
      'no_hp',
      'Nomor HP',
      Icons.phone_outlined,
      keyboardType: TextInputType.phone,
    ),
    const SizedBox(height: 10),
    _field('alamat', 'Alamat', Icons.home_outlined, maxLines: 3),
    const SizedBox(height: 10),
    _field('pendidikan_terakhir', 'Pendidikan terakhir', Icons.school_outlined),
    const SizedBox(height: 10),
    _field(
      'jurusan_pendidikan',
      'Jurusan pendidikan',
      Icons.menu_book_outlined,
    ),
    const SizedBox(height: 10),
    _field(
      'tahun_lulus',
      'Tahun lulus',
      Icons.event_available_outlined,
      keyboardType: TextInputType.number,
    ),
    const SizedBox(height: 10),
    _field(
      'keterangan',
      'Keterangan tambahan',
      Icons.notes_outlined,
      maxLines: 3,
    ),
  ];

  List<Widget> _parentFields() => [
    _field(
      'nama_lengkap',
      'Nama lengkap',
      Icons.person_outline_rounded,
      required: true,
    ),
    const SizedBox(height: 10),
    _field(
      'nomor_wa',
      'Nomor WhatsApp',
      Icons.phone_outlined,
      keyboardType: TextInputType.phone,
    ),
  ];

  List<Widget> _studentFields() => [
    Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: NusaColors.surfaceBlue,
        borderRadius: BorderRadius.circular(13),
        border: Border.all(color: NusaColors.outline),
      ),
      child: const Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.info_outline, color: NusaColors.primary, size: 20),
          SizedBox(width: 9),
          Expanded(
            child: Text(
              'Nama, NIS, NISN, dan identitas sekolah dikelola oleh sekolah. Hubungi wali kelas atau administrator jika ada kekeliruan.',
              style: TextStyle(fontSize: 12, height: 1.45),
            ),
          ),
        ],
      ),
    ),
    const SizedBox(height: 12),
    _field('alamat', 'Alamat', Icons.home_outlined, maxLines: 4),
  ];

  List<Widget> _accountFields() => [
    _field(
      'nama_lengkap',
      'Nama tampilan',
      Icons.person_outline_rounded,
      required: true,
    ),
  ];

  Widget _field(
    String key,
    String label,
    IconData icon, {
    bool required = false,
    int maxLines = 1,
    TextInputType? keyboardType,
  }) {
    return TextFormField(
      key: Key('profile-field-$key'),
      controller: _fields[key],
      enabled: !_saving,
      maxLines: maxLines,
      keyboardType: keyboardType,
      textCapitalization: key == 'email'
          ? TextCapitalization.none
          : TextCapitalization.sentences,
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: Icon(icon),
        errorText: _fieldError(key),
        alignLabelWithHint: maxLines > 1,
      ),
      validator: required
          ? (value) => value == null || value.trim().isEmpty
                ? '$label wajib diisi.'
                : null
          : null,
    );
  }

  Widget _readOnly(String label, String? value, IconData icon) {
    return InputDecorator(
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: Icon(icon),
        enabled: false,
      ),
      child: Text(value ?? '-'),
    );
  }

  Widget _dateField() {
    return TextFormField(
      key: const Key('profile-field-tanggal_lahir'),
      controller: _fields['tanggal_lahir'],
      enabled: !_saving,
      readOnly: true,
      decoration: InputDecoration(
        labelText: 'Tanggal lahir',
        prefixIcon: const Icon(Icons.cake_outlined),
        suffixIcon: const Icon(Icons.calendar_month_outlined),
        errorText: _fieldError('tanggal_lahir'),
      ),
      onTap: _pickBirthDate,
    );
  }

  Future<void> _pickBirthDate() async {
    final current = DateTime.tryParse(_fields['tanggal_lahir']!.text);
    final selected = await showDatePicker(
      context: context,
      initialDate: current ?? DateTime(1990),
      firstDate: DateTime(1940),
      lastDate: DateTime.now(),
    );
    if (selected != null) {
      _fields['tanggal_lahir']!.text = _isoDate(selected);
    }
  }

  String? _fieldError(String key) => _serverErrors[key]?.firstOrNull;

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    FocusScope.of(context).unfocus();
    setState(() {
      _saving = true;
      _error = null;
      _serverErrors = const {};
    });

    try {
      await widget.onSave(_payload());
    } on ValidationException catch (exception) {
      if (mounted) {
        setState(() {
          _error = exception.message;
          _serverErrors = exception.errors;
        });
      }
    } on AppException catch (exception) {
      if (mounted) setState(() => _error = exception.message);
    } catch (error) {
      if (mounted) setState(() => _error = 'Profil gagal disimpan.');
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Map<String, dynamic> _payload() {
    String value(String key) => _fields[key]!.text.trim();

    return switch (widget.profile.kind) {
      MyProfileKind.employee => {
        'nama_lengkap': value('nama_lengkap'),
        'nuptk': value('nuptk'),
        'nik': value('nik'),
        'jenis_kelamin': _gender ?? '',
        'tempat_lahir': value('tempat_lahir'),
        'tanggal_lahir': value('tanggal_lahir'),
        'alamat': value('alamat'),
        'email': value('email'),
        'no_hp': value('no_hp'),
        'pendidikan_terakhir': value('pendidikan_terakhir'),
        'jurusan_pendidikan': value('jurusan_pendidikan'),
        'tahun_lulus': value('tahun_lulus'),
        'keterangan': value('keterangan'),
      },
      MyProfileKind.parent => {
        'nama_lengkap': value('nama_lengkap'),
        'nomor_wa': value('nomor_wa'),
      },
      MyProfileKind.student => {'alamat': value('alamat')},
      MyProfileKind.account => {'nama_lengkap': value('nama_lengkap')},
    };
  }
}

class _PhotoSourceSheet extends StatelessWidget {
  const _PhotoSourceSheet();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(18, 8, 18, 20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Center(
            child: Container(
              width: 42,
              height: 4,
              decoration: BoxDecoration(
                color: NusaColors.outline,
                borderRadius: BorderRadius.circular(99),
              ),
            ),
          ),
          const SizedBox(height: 18),
          const Text(
            'Pilih Foto Profil',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 10),
          ListTile(
            leading: const Icon(Icons.photo_camera_outlined),
            title: const Text('Ambil dari kamera'),
            onTap: () => Navigator.pop(context, MyProfilePhotoSource.camera),
          ),
          ListTile(
            leading: const Icon(Icons.photo_library_outlined),
            title: const Text('Pilih dari galeri'),
            onTap: () => Navigator.pop(context, MyProfilePhotoSource.gallery),
          ),
        ],
      ),
    );
  }
}

class _ProfileLoading extends StatelessWidget {
  const _ProfileLoading({required this.user});

  final Pengguna user;

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(18),
      children: [
        const SizedBox(height: 44),
        Center(child: _Avatar(name: user.nama, size: 82)),
        const SizedBox(height: 14),
        Text(
          user.nama,
          textAlign: TextAlign.center,
          style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 24),
        const Center(child: CircularProgressIndicator()),
      ],
    );
  }
}

class _ProfileError extends StatelessWidget {
  const _ProfileError({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(24),
      children: [
        const SizedBox(height: 50),
        const Icon(
          Icons.person_off_outlined,
          size: 52,
          color: NusaColors.textSecondary,
        ),
        const SizedBox(height: 12),
        Text(message, textAlign: TextAlign.center),
        const SizedBox(height: 16),
        FilledButton(onPressed: onRetry, child: const Text('Coba Lagi')),
      ],
    );
  }
}

String _initials(String name) {
  final parts = name
      .trim()
      .split(RegExp(r'\s+'))
      .where((item) => item.isNotEmpty);
  final value = parts.take(2).map((item) => item[0].toUpperCase()).join();
  return value.isEmpty ? 'N' : value;
}

String _gender(String? value) => switch (value) {
  'L' => 'Laki-laki',
  'P' => 'Perempuan',
  _ => '-',
};

String _birth(String? place, String? date) {
  final values = [
    if (place?.trim().isNotEmpty == true) place!.trim(),
    if (date?.trim().isNotEmpty == true) _date(date),
  ];
  return values.isEmpty ? '-' : values.join(', ');
}

String _date(String? value) {
  final date = DateTime.tryParse(value ?? '');
  if (date == null) return value ?? '-';
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
  return '${date.day} ${months[date.month - 1]} ${date.year}';
}

String _dateTime(DateTime? value) {
  if (value == null) return '-';
  final local = value.toLocal();
  return '${_date(_isoDate(local))}, '
      '${local.hour.toString().padLeft(2, '0')}:'
      '${local.minute.toString().padLeft(2, '0')} WIB';
}

String _isoDate(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-'
    '${value.month.toString().padLeft(2, '0')}-'
    '${value.day.toString().padLeft(2, '0')}';

String _errorMessage(Object error) => switch (error) {
  AppException() => error.message,
  _ => 'Profil belum dapat dimuat. Periksa koneksi lalu coba lagi.',
};

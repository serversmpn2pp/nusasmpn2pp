import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';

class GantiKataSandiView extends ConsumerStatefulWidget {
  const GantiKataSandiView({super.key});

  @override
  ConsumerState<GantiKataSandiView> createState() => _GantiKataSandiViewState();
}

class _GantiKataSandiViewState extends ConsumerState<GantiKataSandiView> {
  final _formKey = GlobalKey<FormState>();
  final _oldPasswordController = TextEditingController();
  final _newPasswordController = TextEditingController();
  final _confirmationController = TextEditingController();
  bool _obscureOld = true;
  bool _obscureNew = true;

  @override
  void dispose() {
    _oldPasswordController.dispose();
    _newPasswordController.dispose();
    _confirmationController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    FocusScope.of(context).unfocus();
    await ref
        .read(authControllerProvider.notifier)
        .ubahKataSandi(
          kataSandiLama: _oldPasswordController.text,
          kataSandiBaru: _newPasswordController.text,
          konfirmasiKataSandiBaru: _confirmationController.text,
        );
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authControllerProvider).value;
    final isSubmitting = auth?.isSubmitting ?? false;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Ganti kata sandi'),
        actions: [
          IconButton(
            onPressed: isSubmitting
                ? null
                : () => ref.read(authControllerProvider.notifier).logout(),
            icon: const Icon(Icons.logout),
            tooltip: 'Keluar',
          ),
        ],
      ),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 520),
              child: Card(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Form(
                    key: _formKey,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text(
                          'Amankan akun Anda',
                          style: Theme.of(context).textTheme.headlineSmall,
                        ),
                        const SizedBox(height: 8),
                        const Text(
                          'Kata sandi awal atau default harus diganti sebelum '
                          'Anda menggunakan fitur NUSA.',
                        ),
                        if (auth?.errorMessage case final message?) ...[
                          const SizedBox(height: 16),
                          Text(
                            message,
                            style: TextStyle(
                              color: Theme.of(context).colorScheme.error,
                            ),
                          ),
                        ],
                        const SizedBox(height: 24),
                        TextFormField(
                          controller: _oldPasswordController,
                          enabled: !isSubmitting,
                          obscureText: _obscureOld,
                          textInputAction: TextInputAction.next,
                          decoration: InputDecoration(
                            labelText: 'Kata sandi lama',
                            errorText: auth?.fieldError('kata_sandi_lama'),
                            suffixIcon: IconButton(
                              onPressed: () =>
                                  setState(() => _obscureOld = !_obscureOld),
                              icon: Icon(
                                _obscureOld
                                    ? Icons.visibility_outlined
                                    : Icons.visibility_off_outlined,
                              ),
                            ),
                          ),
                          validator: _requiredPassword,
                        ),
                        const SizedBox(height: 16),
                        TextFormField(
                          controller: _newPasswordController,
                          enabled: !isSubmitting,
                          obscureText: _obscureNew,
                          textInputAction: TextInputAction.next,
                          decoration: InputDecoration(
                            labelText: 'Kata sandi baru',
                            helperText: 'Minimal 8 karakter',
                            errorText: auth?.fieldError('kata_sandi_baru'),
                            suffixIcon: IconButton(
                              onPressed: () =>
                                  setState(() => _obscureNew = !_obscureNew),
                              icon: Icon(
                                _obscureNew
                                    ? Icons.visibility_outlined
                                    : Icons.visibility_off_outlined,
                              ),
                            ),
                          ),
                          validator: (value) {
                            if (value == null || value.isEmpty) {
                              return 'Kata sandi baru wajib diisi.';
                            }
                            if (value.length < 8) {
                              return 'Kata sandi baru minimal 8 karakter.';
                            }
                            if (value == _oldPasswordController.text) {
                              return 'Gunakan kata sandi yang berbeda.';
                            }
                            return null;
                          },
                        ),
                        const SizedBox(height: 16),
                        TextFormField(
                          controller: _confirmationController,
                          enabled: !isSubmitting,
                          obscureText: _obscureNew,
                          textInputAction: TextInputAction.done,
                          onFieldSubmitted: (_) => _submit(),
                          decoration: const InputDecoration(
                            labelText: 'Konfirmasi kata sandi baru',
                          ),
                          validator: (value) =>
                              value != _newPasswordController.text
                              ? 'Konfirmasi kata sandi baru tidak sama.'
                              : _requiredPassword(value),
                        ),
                        const SizedBox(height: 24),
                        FilledButton(
                          onPressed: isSubmitting ? null : _submit,
                          child: isSubmitting
                              ? const SizedBox.square(
                                  dimension: 22,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2.5,
                                    color: Colors.white,
                                  ),
                                )
                              : const Text('Simpan kata sandi'),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  String? _requiredPassword(String? value) {
    return value == null || value.isEmpty ? 'Kata sandi wajib diisi.' : null;
  }
}

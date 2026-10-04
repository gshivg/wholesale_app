import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/app_config.dart';
import '../../core/format.dart';
import '../../core/theme.dart';
import '../../l10n/app_localizations.dart';
import '../../models/models.dart';
import '../../providers/providers.dart';
import '../../widgets/widgets.dart';
import '../shell_screen.dart';

class SupportScreen extends ConsumerWidget {
  const SupportScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final tickets = ref.watch(ticketsProvider);
    return Scaffold(
      appBar: AppBar(title: Text(l.support), actions: const [AppBarActions()]),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: context.gold,
        foregroundColor: context.cs.onTertiary,
        onPressed: () => context.push('/support/new'),
        icon: const Icon(Icons.add),
        label: Text(l.newTicket),
      ),
      body: AsyncBody<List<Ticket>>(
        value: tickets,
        onRetry: () => ref.invalidate(ticketsProvider),
        builder: (list) => list.isEmpty
            ? EmptyState(icon: Icons.support_agent_outlined, title: l.noTickets, subtitle: 'Damaged goods, missing items, delays or payment problems — raise a ticket and our team will respond.')
            : RefreshIndicator(
                onRefresh: () async => ref.invalidate(ticketsProvider),
                child: ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 96),
                  itemCount: list.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 10),
                  itemBuilder: (_, i) {
                    final t = list[i];
                    return Card(
                      clipBehavior: Clip.antiAlias,
                      child: InkWell(
                        onTap: () => context.push('/support/${t.id}'),
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Row(children: [
                            Container(width: 40, height: 40, decoration: BoxDecoration(color: context.cs.primaryContainer, shape: BoxShape.circle), child: Icon(t.isOpen ? Icons.chat_bubble_outline : Icons.task_alt, color: context.cs.primary)),
                            const SizedBox(width: 14),
                            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Text(t.subject, style: context.tt.titleSmall, maxLines: 1, overflow: TextOverflow.ellipsis),
                              Text('${t.number} · ${t.categoryLabel}${t.lastReplyAt != null ? ' · ${timeAgo(t.lastReplyAt!)}' : ''}', style: TextStyle(color: context.cs.onSurfaceVariant, fontSize: 12)),
                            ])),
                            StatusChip(t.status),
                          ]),
                        ),
                      ),
                    );
                  },
                ),
              ),
      ),
    );
  }
}

class NewTicketScreen extends ConsumerStatefulWidget {
  const NewTicketScreen({super.key, this.orderId});
  final int? orderId;
  @override
  ConsumerState<NewTicketScreen> createState() => _NewTicketScreenState();
}

class _NewTicketScreenState extends ConsumerState<NewTicketScreen> {
  final _form = GlobalKey<FormState>();
  final _subject = TextEditingController();
  final _message = TextEditingController();
  late String _category = widget.orderId != null ? 'damaged' : 'other';
  int? _orderId;
  final List<XFile> _photos = [];
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _orderId = widget.orderId;
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      final t = await ref.read(ticketActionsProvider).create(category: _category, subject: _subject.text.trim(), message: _message.text.trim(), orderId: _orderId, photos: _photos);
      if (!mounted) return;
      showSnack(context, 'Ticket ${t.number} created');
      context.pushReplacement('/support/${t.id}');
    } catch (e) {
      if (mounted) showSnack(context, errorText(e), error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final orders = ref.watch(ordersProvider('all')).value ?? const <OrderSummary>[];
    return Scaffold(
      appBar: AppBar(title: const Text('New ticket')),
      body: Form(
        key: _form,
        child: ListView(padding: const EdgeInsets.all(16), children: [
          DropdownButtonFormField<String>(
            initialValue: _category, isExpanded: true, decoration: const InputDecoration(labelText: "What's it about?"),
            items: [for (final c in AppConfig.instance.ticketCategories) DropdownMenuItem(value: c.key, child: Text(c.label))],
            onChanged: (v) => setState(() => _category = v!),
          ),
          const SizedBox(height: 16),
          DropdownButtonFormField<int?>(
            initialValue: orders.any((o) => o.id == _orderId) ? _orderId : null, isExpanded: true, decoration: const InputDecoration(labelText: 'Related order (optional)'),
            items: [const DropdownMenuItem<int?>(value: null, child: Text('— none —')), for (final o in orders) DropdownMenuItem<int?>(value: o.id, child: Text('${o.number} · ${inr(o.total)}'))],
            onChanged: (v) => setState(() => _orderId = v),
          ),
          const SizedBox(height: 16),
          TextFormField(controller: _subject, decoration: const InputDecoration(labelText: 'Subject'), validator: (v) => (v ?? '').trim().isEmpty ? 'Required' : null),
          const SizedBox(height: 16),
          TextFormField(controller: _message, maxLines: 5, decoration: const InputDecoration(labelText: 'Describe the problem', alignLabelWithHint: true), validator: (v) => (v ?? '').trim().length < 5 ? 'Please add a few details' : null),
          const SizedBox(height: 16),
          Wrap(spacing: 8, runSpacing: 8, children: [
            for (final p in _photos) Chip(label: Text(p.name, overflow: TextOverflow.ellipsis), onDeleted: () => setState(() => _photos.remove(p))),
            if (_photos.length < 4) ActionChip(avatar: const Icon(Icons.add_a_photo_outlined, size: 18), label: const Text('Add photo'), onPressed: () async {
              final p = await ImagePicker().pickImage(source: ImageSource.gallery, maxWidth: 1600, imageQuality: 85);
              if (p != null) setState(() => _photos.add(p));
            }),
          ]),
          const SizedBox(height: 24),
          FilledButton(onPressed: _busy ? null : _submit, child: _busy ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Submit ticket')),
        ]),
      ),
    );
  }
}

class TicketScreen extends ConsumerStatefulWidget {
  const TicketScreen({super.key, required this.id});
  final int id;
  @override
  ConsumerState<TicketScreen> createState() => _TicketScreenState();
}

class _TicketScreenState extends ConsumerState<TicketScreen> {
  final _reply = TextEditingController();
  bool _busy = false;

  Future<void> _send() async {
    final text = _reply.text.trim();
    if (text.isEmpty) return;
    setState(() => _busy = true);
    try {
      await ref.read(ticketActionsProvider).reply(widget.id, text, const []);
      _reply.clear();
    } catch (e) {
      if (mounted) showSnack(context, errorText(e), error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final async = ref.watch(ticketProvider(widget.id));
    final cs = context.cs;
    return Scaffold(
      appBar: AppBar(title: Text(async.value?.number ?? 'Ticket'), actions: [
        if (async.value != null && async.value!.status != 'closed')
          PopupMenuButton<String>(
            onSelected: (_) async {
              await ref.read(ticketActionsProvider).close(widget.id);
              if (context.mounted) showSnack(context, 'Ticket closed');
            },
            itemBuilder: (_) => const [PopupMenuItem(value: 'close', child: Text('Close ticket'))],
          ),
      ]),
      body: AsyncBody<Ticket>(
        value: async,
        onRetry: () => ref.invalidate(ticketProvider(widget.id)),
        builder: (t) => Column(children: [
          Container(
            width: double.infinity,
            color: cs.surfaceContainerLow,
            padding: const EdgeInsets.all(16),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [Expanded(child: Text(t.subject, style: context.tt.titleMedium)), StatusChip(t.status)]),
              const SizedBox(height: 4),
              Text('${t.categoryLabel}${t.orderNumber != null ? ' · Order ${t.orderNumber}' : ''} · Agent: ${t.agentName ?? 'being assigned'}', style: TextStyle(color: cs.onSurfaceVariant, fontSize: 12)),
            ]),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () async => ref.invalidate(ticketProvider(widget.id)),
              child: ListView(padding: const EdgeInsets.all(16), children: [
                for (final m in t.messages) _Bubble(m),
                if (!t.isOpen) _RateCard(t),
              ]),
            ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
              child: Row(children: [
                Expanded(child: TextField(controller: _reply, minLines: 1, maxLines: 4, decoration: InputDecoration(hintText: l.reply, isDense: true, border: OutlineInputBorder(borderRadius: BorderRadius.circular(24))))),
                const SizedBox(width: 8),
                IconButton.filled(onPressed: _busy ? null : _send, icon: _busy ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.send)),
              ]),
            ),
          ),
        ]),
      ),
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble(this.m);
  final TicketMessage m;
  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    final mine = !m.fromStaff;
    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * .8),
        decoration: BoxDecoration(
          color: mine ? cs.primary : cs.surfaceContainerHigh,
          borderRadius: BorderRadius.only(topLeft: const Radius.circular(18), topRight: const Radius.circular(18), bottomLeft: Radius.circular(mine ? 18 : 4), bottomRight: Radius.circular(mine ? 4 : 18)),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('${m.author} · ${fmtDateTime(m.at)}', style: TextStyle(fontSize: 11, color: mine ? Colors.white70 : cs.onSurfaceVariant)),
          const SizedBox(height: 2),
          Text(m.body, style: TextStyle(color: mine ? Colors.white : cs.onSurface)),
          for (final a in m.attachments) Padding(padding: const EdgeInsets.only(top: 8), child: ClipRRect(borderRadius: BorderRadius.circular(8), child: Image.network(a, height: 120, errorBuilder: (_, _, _) => const Icon(Icons.broken_image)))),
        ]),
      ),
    );
  }
}

class _RateCard extends ConsumerWidget {
  const _RateCard(this.t);
  final Ticket t;
  @override
  Widget build(BuildContext context, WidgetRef ref) => Card(
        color: context.cs.tertiaryContainer,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(children: [
            Text('This ticket is ${t.status}. Reply below to reopen it.', style: TextStyle(color: context.cs.onTertiaryContainer)),
            const SizedBox(height: 6),
            Row(mainAxisAlignment: MainAxisAlignment.center, children: [
              for (var n = 1; n <= 5; n++)
                IconButton(
                  onPressed: () async {
                    await ref.read(ticketActionsProvider).rate(t.id, n);
                    if (context.mounted) showSnack(context, 'Thanks for your feedback!');
                  },
                  icon: Icon((t.rating ?? 0) >= n ? Icons.star : Icons.star_border, color: const Color(0xFFB8860B)),
                ),
            ]),
          ]),
        ),
      );
}

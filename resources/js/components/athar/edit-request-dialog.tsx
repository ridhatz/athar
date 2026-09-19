import { router } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useFormat } from '@/hooks/use-format';
import { useT } from '@/hooks/use-t';
import { update } from '@/routes/requests';
import type { Category, RequestDetail } from '@/types/athar';
import { REQUEST_PRIORITIES } from '@/types/athar';

/**
 * Edit details. Which fields appear follows the server's `permissions`: a requester before triage
 * gets everything; an admin also gets priority; a team lead gets details but not priority.
 */
export function EditRequestDialog({
    request,
    categories,
}: {
    request: RequestDetail;
    categories: Category[];
}) {
    const { t } = useT();
    const format = useFormat();
    const initialDay = format.toInputDate(request.deadline);

    const [open, setOpen] = useState(false);
    const [title, setTitle] = useState(request.title);
    const [description, setDescription] = useState(request.description);
    const [categoryId, setCategoryId] = useState(
        String(request.category?.id ?? ''),
    );
    const [day, setDay] = useState(initialDay);
    const [priority, setPriority] = useState<string>(request.priority);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const canEditDetails = request.permissions.edit_details;
    const canEditPriority = request.permissions.edit_priority;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        setProcessing(true);

        const changes: Record<string, string | number> = {};
        if (canEditDetails) {
            changes.title = title;
            changes.description = description;
            changes.category_id = Number(categoryId);
            // Only send the deadline if the DAY changed, so saving other edits never shifts its time of day.
            if (day !== initialDay) {
                changes.deadline = format.endOfDay(day);
            }
        }
        if (canEditPriority) {
            changes.priority = priority;
        }

        router.patch(
            update({ serviceRequest: request.request_number }).url,
            changes,
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);
                    setErrors({});
                },
                onError: (serverErrors) => setErrors(serverErrors),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Pencil />
                    {t('show.edit')}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>{t('edit.title')}</DialogTitle>
                    </DialogHeader>

                    {canEditDetails && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="edit-title">
                                    {t('form.field.title')}
                                </Label>
                                <Input
                                    id="edit-title"
                                    value={title}
                                    onChange={(e) => setTitle(e.target.value)}
                                    maxLength={200}
                                />
                                <InputError message={errors.title} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="edit-category">
                                        {t('form.field.category')}
                                    </Label>
                                    <Select
                                        value={categoryId}
                                        onValueChange={setCategoryId}
                                    >
                                        <SelectTrigger id="edit-category">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {categories.map((category) => (
                                                <SelectItem
                                                    key={category.id}
                                                    value={String(category.id)}
                                                >
                                                    {category.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.category_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="edit-deadline">
                                        {t('form.field.deadline')}
                                    </Label>
                                    <Input
                                        id="edit-deadline"
                                        type="date"
                                        value={day}
                                        min={format.today()}
                                        onChange={(e) => setDay(e.target.value)}
                                    />
                                    <InputError message={errors.deadline} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="edit-description">
                                    {t('form.field.description')}
                                </Label>
                                <Textarea
                                    id="edit-description"
                                    value={description}
                                    onChange={(e) =>
                                        setDescription(e.target.value)
                                    }
                                    maxLength={10000}
                                    className="min-h-32"
                                />
                                <InputError message={errors.description} />
                            </div>
                        </>
                    )}

                    {canEditPriority && (
                        <div className="grid gap-2">
                            <Label htmlFor="edit-priority">
                                {t('form.field.priority')}
                            </Label>
                            <Select
                                value={priority}
                                onValueChange={setPriority}
                            >
                                <SelectTrigger id="edit-priority">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {REQUEST_PRIORITIES.map((value) => (
                                        <SelectItem key={value} value={value}>
                                            {t(`priority.${value}`)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.priority} />
                        </div>
                    )}

                    <InputError message={errors.status} />

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setOpen(false)}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {t('edit.submit')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

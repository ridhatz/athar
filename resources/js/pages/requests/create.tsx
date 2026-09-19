import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ChevronDown } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
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
import { index, store } from '@/routes/requests';
import type { Category } from '@/types/athar';

type Props = {
    categories: Category[];
    priorities: string[];
    departments: { id: number; name: string }[];
    ownDepartment: string | null;
};

/**
 * Four things are required: what, category, when, and details. Everything else is tucked
 * under "More options", so filing a request takes a minute, not a form-filling session.
 */
export default function CreateRequest({
    categories,
    priorities,
    departments,
    ownDepartment,
}: Props) {
    const { t } = useT();
    const format = useFormat();

    const form = useForm({
        title: '',
        category_id: '',
        day: '',
        description: '',
        priority: 'NORMAL',
        department_id: '',
    });

    // The server reports the deadline under its own name, while this form edits it as a plain day.
    const errors = form.errors as Record<string, string | undefined>;

    const category = categories.find(
        (c) => String(c.id) === form.data.category_id,
    );
    const needsDepartment = departments.length > 0;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.transform((data) => ({
            title: data.title,
            category_id: data.category_id ? Number(data.category_id) : null,
            deadline: data.day ? format.endOfDay(data.day) : null,
            description: data.description,
            priority: data.priority,
            department_id: data.department_id
                ? Number(data.department_id)
                : null,
        }));

        form.post(store().url);
    };

    return (
        <>
            <Head title={t('form.title')} />

            <div className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 md:p-6">
                <div>
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="mb-2 -ml-3"
                    >
                        <Link href={index()}>
                            <ArrowLeft />
                            {t('show.back')}
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('form.title')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t('form.subtitle')}
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    className="flex flex-col gap-5"
                    noValidate
                >
                    <div className="grid gap-2">
                        <Label htmlFor="title">{t('form.field.title')}</Label>
                        <Input
                            id="title"
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData('title', e.target.value)
                            }
                            placeholder={t('form.field.title_placeholder')}
                            maxLength={200}
                            autoFocus
                            aria-invalid={!!form.errors.title}
                        />
                        <InputError message={form.errors.title} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="category">
                                {t('form.field.category')}
                            </Label>
                            <Select
                                value={form.data.category_id}
                                onValueChange={(v) =>
                                    form.setData('category_id', v)
                                }
                            >
                                <SelectTrigger
                                    id="category"
                                    aria-invalid={!!form.errors.category_id}
                                >
                                    <SelectValue
                                        placeholder={t(
                                            'form.field.category_placeholder',
                                        )}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {categories.map((option) => (
                                        <SelectItem
                                            key={option.id}
                                            value={String(option.id)}
                                        >
                                            {option.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.category_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="day">
                                {t('form.field.deadline')}
                            </Label>
                            <Input
                                id="day"
                                type="date"
                                value={form.data.day}
                                min={format.today()}
                                onChange={(e) =>
                                    form.setData('day', e.target.value)
                                }
                                aria-invalid={!!errors.deadline}
                            />
                            <InputError message={errors.deadline} />
                        </div>
                    </div>

                    {/* Category-specific guidance appears only once a category is chosen. */}
                    {category?.guidance && (
                        <p
                            className="bg-muted text-muted-foreground rounded-md px-3 py-2 text-sm"
                            role="note"
                        >
                            {category.guidance}
                        </p>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="description">
                            {t('form.field.description')}
                        </Label>
                        <Textarea
                            id="description"
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                            maxLength={10000}
                            className="min-h-40"
                            aria-invalid={!!form.errors.description}
                            aria-describedby="description-hint"
                        />
                        <p
                            id="description-hint"
                            className="text-muted-foreground text-xs"
                        >
                            {t('form.field.description_hint')}
                        </p>
                        <InputError message={form.errors.description} />
                    </div>

                    <Collapsible
                        defaultOpen={
                            needsDepartment ||
                            !!form.errors.priority ||
                            !!form.errors.department_id
                        }
                    >
                        <CollapsibleTrigger className="text-muted-foreground hover:text-foreground group flex items-center gap-1 text-sm font-medium">
                            <ChevronDown
                                className="size-4 transition-transform group-data-[state=open]:rotate-180"
                                aria-hidden
                            />
                            {t('form.more')}
                            <span className="font-normal">
                                ({t('common.optional')})
                            </span>
                        </CollapsibleTrigger>
                        <CollapsibleContent className="mt-4 grid gap-5">
                            <div className="grid gap-2">
                                <Label htmlFor="priority">
                                    {t('form.field.priority')}
                                </Label>
                                <Select
                                    value={form.data.priority}
                                    onValueChange={(v) =>
                                        form.setData('priority', v)
                                    }
                                >
                                    <SelectTrigger
                                        id="priority"
                                        className="sm:w-56"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {priorities.map((value) => (
                                            <SelectItem
                                                key={value}
                                                value={value}
                                            >
                                                {t(`priority.${value}`)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-muted-foreground text-xs">
                                    {t('form.field.priority_hint')}
                                </p>
                                <InputError message={form.errors.priority} />
                            </div>

                            {needsDepartment ? (
                                <div className="grid gap-2">
                                    <Label htmlFor="department">
                                        {t('form.field.department')}
                                    </Label>
                                    <Select
                                        value={form.data.department_id}
                                        onValueChange={(v) =>
                                            form.setData('department_id', v)
                                        }
                                    >
                                        <SelectTrigger
                                            id="department"
                                            className="sm:w-72"
                                            aria-invalid={
                                                !!form.errors.department_id
                                            }
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {departments.map((department) => (
                                                <SelectItem
                                                    key={department.id}
                                                    value={String(
                                                        department.id,
                                                    )}
                                                >
                                                    {department.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={form.errors.department_id}
                                    />
                                </div>
                            ) : (
                                ownDepartment && (
                                    <p className="text-muted-foreground text-sm">
                                        {t('form.department_own', {
                                            name: ownDepartment,
                                        })}
                                    </p>
                                )
                            )}
                        </CollapsibleContent>
                    </Collapsible>

                    <div className="flex justify-end gap-2">
                        <Button asChild variant="ghost">
                            <Link href={index()}>{t('common.cancel')}</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {form.processing
                                ? t('form.submitting')
                                : t('form.submit')}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

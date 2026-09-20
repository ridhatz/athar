import { router } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { LevelDot } from '@/components/athar/urgency-bar';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/hooks/use-t';
import { assign } from '@/routes/requests';
import type { Assignables, RequestDetail } from '@/types/athar';

const NO_PIC = 'none';

/** Route a request to a team and (optionally) a person. Only shown to those the server lets assign. */
export function AssignPanel({
    request,
    assignables,
}: {
    request: RequestDetail;
    assignables: Assignables;
}) {
    const { t } = useT();

    const initialTeam = request.assigned_team
        ? String(request.assigned_team.id)
        : '';
    const initialUser = request.assigned_user
        ? String(request.assigned_user.id)
        : NO_PIC;

    const [team, setTeam] = useState(initialTeam);
    const [user, setUser] = useState(initialUser);
    const [note, setNote] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const people = assignables.users.filter(
        (person) => team !== '' && person.team_ids.includes(Number(team)),
    );
    const dirty = team !== '' && (team !== initialTeam || user !== initialUser);

    const changeTeam = (value: string) => {
        setTeam(value);
        // A PIC belongs to a team; if the new team does not include them, clear the choice.
        const stillMember = assignables.users.some(
            (p) => String(p.id) === user && p.team_ids.includes(Number(value)),
        );
        if (!stillMember) {
            setUser(NO_PIC);
        }
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        setProcessing(true);
        router.post(
            assign({ serviceRequest: request.request_number }).url,
            {
                team_id: Number(team),
                user_id: user === NO_PIC ? null : Number(user),
                note: note || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setNote('');
                    setErrors({});
                },
                onError: (serverErrors) => setErrors(serverErrors),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-2">
                <Label htmlFor="assign-team">{t('assign.team')}</Label>
                <Select value={team} onValueChange={changeTeam}>
                    <SelectTrigger
                        id="assign-team"
                        aria-invalid={!!errors.team_id}
                    >
                        <SelectValue
                            placeholder={t('assign.team_placeholder')}
                        />
                    </SelectTrigger>
                    <SelectContent>
                        {assignables.teams.map((option) => (
                            <SelectItem
                                key={option.id}
                                value={String(option.id)}
                            >
                                {option.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.team_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="assign-pic">{t('assign.pic')}</Label>
                <Select
                    value={user}
                    onValueChange={setUser}
                    disabled={team === ''}
                >
                    <SelectTrigger
                        id="assign-pic"
                        aria-invalid={!!errors.user_id}
                    >
                        <SelectValue
                            placeholder={t('assign.pic_placeholder')}
                        />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={NO_PIC}>
                            {t('assign.pic_none')}
                        </SelectItem>
                        {people.map((person) => (
                            <SelectItem
                                key={person.id}
                                value={String(person.id)}
                            >
                                <span className="flex items-center gap-2">
                                    <LevelDot level={person.level} />
                                    {person.name}
                                    <span className="text-muted-foreground text-xs">
                                        {t('assign.load', { n: person.open })}
                                        {person.level === 'red' &&
                                            ` · ${t('assign.load_red')}`}
                                    </span>
                                </span>
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.user_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="assign-note">
                    {t('assign.note')}{' '}
                    <span className="text-muted-foreground font-normal">
                        ({t('common.optional')})
                    </span>
                </Label>
                <Input
                    id="assign-note"
                    value={note}
                    onChange={(event) => setNote(event.target.value)}
                    maxLength={500}
                />
                <InputError message={errors.note} />
            </div>

            {request.status === 'NEW' && (
                <p className="text-muted-foreground text-xs">
                    {t('assign.hint')}
                </p>
            )}

            <Button
                type="submit"
                className="w-full"
                disabled={!dirty || processing}
            >
                {processing && <Spinner />}
                {t('assign.submit')}
            </Button>
        </form>
    );
}

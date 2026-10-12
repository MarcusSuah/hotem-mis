import { Head, router, useForm } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plane, Plus, Users, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { Field } from '@/components/admin/field';
import { FormDialog } from '@/components/admin/form-dialog';
import { FormErrorSummary } from '@/components/admin/form-error-summary';
import { OptionSelect } from '@/components/admin/option-select';
import type { Option } from '@/components/admin/option-select';
import { PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { notifyValidationFailed } from '@/lib/form';
import { formatDate } from '@/lib/format';
import admin from '@/routes/admin';

type Staff = {
    id: number;
    employee_number: string;
    first_name: string;
    last_name: string;
    job_title: string;
    email: string | null;
    phone: string | null;
    department_id: number;
    user_id: number | null;
    is_active: boolean;
    name: string;
    department: string;
    employment_type: string;
    employment_label: string;
    hire_date: string;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    account: { id: number; name: string; email: string } | null;
};

type Shift = {
    id: number;
    staff_profile_id: number;
    date: string;
    starts_at: string;
    ends_at: string;
    role: string | null;
};
type Attendance = {
    staff_profile_id: number;
    date: string;
    status: string;
    status_label: string;
};
type Leave = {
    id: number;
    staff: string;
    type_label: string;
    start_date: string;
    end_date: string;
    reason: string | null;
    status: string;
    status_label: string;
    decision_note: string | null;
};
type Department = {
    id: number;
    name: string;
    description: string | null;
    staff_count: number;
};
type Account = { id: number; name: string; email: string };

type Props = {
    week: string;
    days: string[];
    filters: { department: string };
    staff: Staff[];
    shifts: Shift[];
    attendance: Attendance[];
    leave: Leave[];
    departments: Department[];
    accounts: Account[];
    options: {
        employmentTypes: Option[];
        attendance: Option[];
        leaveTypes: Option[];
    };
};

const attendanceVariant: Record<
    string,
    'success' | 'warning' | 'danger' | 'muted' | 'secondary'
> = {
    present: 'success',
    late: 'warning',
    absent: 'danger',
    on_leave: 'muted',
    off: 'secondary',
};
const leaveVariant: Record<string, 'warning' | 'success' | 'danger' | 'muted'> =
    {
        pending: 'warning',
        approved: 'success',
        rejected: 'danger',
        cancelled: 'muted',
    };

function shiftWeek(week: string, days: number): string {
    const date = new Date(`${week}T00:00:00`);
    date.setDate(date.getDate() + days);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

export default function StaffIndex(props: Props) {
    const {
        week,
        days,
        staff,
        shifts,
        attendance,
        leave,
        departments,
        options,
    } = props;
    const [editing, setEditing] = useState<Staff | null | undefined>(undefined);
    const [shiftCell, setShiftCell] = useState<{
        staff: Staff;
        date: string;
    } | null>(null);
    const [attendanceCell, setAttendanceCell] = useState<{
        staff: Staff;
        date: string;
    } | null>(null);
    const [requestingLeave, setRequestingLeave] = useState(false);
    const [deciding, setDeciding] = useState<{
        leave: Leave;
        status: 'approved' | 'rejected';
    } | null>(null);
    const [addingDepartment, setAddingDepartment] = useState(false);
    const today = new Date().toISOString().slice(0, 10);
    const activeStaff = staff.filter((member) => member.is_active);

    function visit(query: Record<string, string>) {
        router.get(
            admin.staff.index.url(),
            { week, department: props.filters.department, ...query },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Staff" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Staff"
                    description="Directory, weekly rota, attendance and leave."
                    actions={
                        <Button
                            onClick={() => setEditing(null)}
                            disabled={departments.length === 0}
                        >
                            <Plus />
                            Add staff member
                        </Button>
                    }
                />

                <Tabs defaultValue="rota" className="gap-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <TabsList>
                            <TabsTrigger value="rota">Rota</TabsTrigger>
                            <TabsTrigger value="directory">
                                Directory
                            </TabsTrigger>
                            <TabsTrigger value="leave">
                                Leave (
                                {
                                    leave.filter(
                                        (row) => row.status === 'pending',
                                    ).length
                                }
                                )
                            </TabsTrigger>
                            <TabsTrigger value="departments">
                                Departments
                            </TabsTrigger>
                        </TabsList>
                        <OptionSelect
                            ariaLabel="Department"
                            className="w-48"
                            value={props.filters.department}
                            placeholder="All departments"
                            options={departments.map((row) => ({
                                value: String(row.id),
                                label: row.name,
                            }))}
                            onChange={(value) => visit({ department: value })}
                        />
                    </div>

                    <TabsContent value="rota">
                        <div className="rounded-xl border bg-card shadow-xs">
                            <div className="flex items-center justify-between border-b p-3">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        visit({ week: shiftWeek(week, -7) })
                                    }
                                >
                                    <ChevronLeft />
                                    Previous
                                </Button>
                                <p className="text-sm font-medium">
                                    {formatDate(days[0])} –{' '}
                                    {formatDate(days[6])}
                                </p>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        visit({ week: shiftWeek(week, 7) })
                                    }
                                >
                                    Next
                                    <ChevronRight />
                                </Button>
                            </div>
                            {activeStaff.length === 0 ? (
                                <EmptyState icon={Users} title="No staff yet" />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[56rem] text-sm">
                                        <thead>
                                            <tr className="border-b text-left text-muted-foreground">
                                                <th className="p-2 font-medium">
                                                    Staff
                                                </th>
                                                {days.map((day) => (
                                                    <th
                                                        key={day}
                                                        className={`p-2 font-medium ${day === today ? 'text-foreground' : ''}`}
                                                    >
                                                        {new Date(
                                                            `${day}T00:00:00`,
                                                        ).toLocaleDateString(
                                                            undefined,
                                                            {
                                                                weekday:
                                                                    'short',
                                                                day: 'numeric',
                                                            },
                                                        )}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {activeStaff.map((member) => (
                                                <tr
                                                    key={member.id}
                                                    className="border-b last:border-0"
                                                >
                                                    <td className="p-2">
                                                        <span className="font-medium">
                                                            {member.name}
                                                        </span>
                                                        <span className="block text-xs text-muted-foreground">
                                                            {member.job_title}
                                                        </span>
                                                    </td>
                                                    {days.map((day) => {
                                                        const dayShifts =
                                                            shifts.filter(
                                                                (shift) =>
                                                                    shift.staff_profile_id ===
                                                                        member.id &&
                                                                    shift.date ===
                                                                        day,
                                                            );
                                                        const record =
                                                            attendance.find(
                                                                (row) =>
                                                                    row.staff_profile_id ===
                                                                        member.id &&
                                                                    row.date ===
                                                                        day,
                                                            );

                                                        return (
                                                            <td
                                                                key={day}
                                                                className={`space-y-1 p-1.5 align-top ${day === today ? 'bg-highlight/10' : ''}`}
                                                            >
                                                                {dayShifts.map(
                                                                    (shift) => (
                                                                        <span
                                                                            key={
                                                                                shift.id
                                                                            }
                                                                            className="group flex items-center justify-between gap-1 rounded bg-primary/10 px-1.5 py-0.5 text-xs"
                                                                        >
                                                                            {
                                                                                shift.starts_at
                                                                            }
                                                                            –
                                                                            {
                                                                                shift.ends_at
                                                                            }
                                                                            <button
                                                                                type="button"
                                                                                className="opacity-0 group-hover:opacity-100 focus:opacity-100"
                                                                                aria-label="Remove shift"
                                                                                onClick={() =>
                                                                                    router.delete(
                                                                                        admin.staff.shifts.destroy.url(
                                                                                            shift.id,
                                                                                        ),
                                                                                        {
                                                                                            preserveScroll: true,
                                                                                        },
                                                                                    )
                                                                                }
                                                                            >
                                                                                <X className="size-3" />
                                                                            </button>
                                                                        </span>
                                                                    ),
                                                                )}
                                                                <div className="flex gap-1">
                                                                    <button
                                                                        type="button"
                                                                        className="rounded px-1 text-xs text-muted-foreground hover:bg-muted"
                                                                        onClick={() =>
                                                                            setShiftCell(
                                                                                {
                                                                                    staff: member,
                                                                                    date: day,
                                                                                },
                                                                            )
                                                                        }
                                                                    >
                                                                        + shift
                                                                    </button>
                                                                    {day <=
                                                                        today &&
                                                                        (record ? (
                                                                            <button
                                                                                type="button"
                                                                                onClick={() =>
                                                                                    setAttendanceCell(
                                                                                        {
                                                                                            staff: member,
                                                                                            date: day,
                                                                                        },
                                                                                    )
                                                                                }
                                                                            >
                                                                                <Badge
                                                                                    variant={
                                                                                        attendanceVariant[
                                                                                            record
                                                                                                .status
                                                                                        ] ??
                                                                                        'secondary'
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        record.status_label
                                                                                    }
                                                                                </Badge>
                                                                            </button>
                                                                        ) : (
                                                                            <button
                                                                                type="button"
                                                                                className="rounded px-1 text-xs text-muted-foreground hover:bg-muted"
                                                                                onClick={() =>
                                                                                    setAttendanceCell(
                                                                                        {
                                                                                            staff: member,
                                                                                            date: day,
                                                                                        },
                                                                                    )
                                                                                }
                                                                            >
                                                                                mark
                                                                            </button>
                                                                        ))}
                                                                </div>
                                                            </td>
                                                        );
                                                    })}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </TabsContent>

                    <TabsContent value="directory">
                        <div className="rounded-xl border bg-card shadow-xs">
                            {staff.length === 0 ? (
                                <EmptyState
                                    icon={Users}
                                    title="No staff records"
                                />
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Name</TableHead>
                                            <TableHead>Role</TableHead>
                                            <TableHead className="hidden md:table-cell">
                                                Contact
                                            </TableHead>
                                            <TableHead className="hidden lg:table-cell">
                                                System account
                                            </TableHead>
                                            <TableHead />
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {staff.map((member) => (
                                            <TableRow
                                                key={member.id}
                                                className={
                                                    member.is_active
                                                        ? undefined
                                                        : 'opacity-60'
                                                }
                                            >
                                                <TableCell>
                                                    {member.name}{' '}
                                                    {!member.is_active && (
                                                        <Badge variant="muted">
                                                            Inactive
                                                        </Badge>
                                                    )}
                                                    <span className="block font-mono text-xs text-muted-foreground">
                                                        {member.employee_number}
                                                    </span>
                                                </TableCell>
                                                <TableCell>
                                                    {member.job_title}
                                                    <span className="block text-xs text-muted-foreground">
                                                        {member.department} ·{' '}
                                                        {
                                                            member.employment_label
                                                        }
                                                    </span>
                                                </TableCell>
                                                <TableCell className="hidden md:table-cell">
                                                    {member.phone ?? '—'}
                                                    {member.email && (
                                                        <span className="block text-xs text-muted-foreground">
                                                            {member.email}
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="hidden lg:table-cell">
                                                    {member.account?.email ??
                                                        '—'}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            setEditing(member)
                                                        }
                                                    >
                                                        Edit
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </div>
                    </TabsContent>

                    <TabsContent value="leave">
                        <div className="rounded-xl border bg-card shadow-xs">
                            <div className="flex justify-end border-b p-3">
                                <Button
                                    size="sm"
                                    onClick={() => setRequestingLeave(true)}
                                    disabled={activeStaff.length === 0}
                                >
                                    <Plane />
                                    Record leave request
                                </Button>
                            </div>
                            {leave.length === 0 ? (
                                <EmptyState
                                    icon={Plane}
                                    title="No current leave"
                                />
                            ) : (
                                <ul className="divide-y text-sm">
                                    {leave.map((row) => (
                                        <li
                                            key={row.id}
                                            className="flex flex-wrap items-center justify-between gap-3 p-4"
                                        >
                                            <div>
                                                <p className="font-medium">
                                                    {row.staff} ·{' '}
                                                    {row.type_label}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatDate(row.start_date)}{' '}
                                                    – {formatDate(row.end_date)}
                                                    {row.reason &&
                                                        ` · ${row.reason}`}
                                                    {row.decision_note &&
                                                        ` · Note: ${row.decision_note}`}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Badge
                                                    variant={
                                                        leaveVariant[
                                                            row.status
                                                        ] ?? 'muted'
                                                    }
                                                >
                                                    {row.status_label}
                                                </Badge>
                                                {row.status === 'pending' && (
                                                    <>
                                                        <Button
                                                            size="sm"
                                                            onClick={() =>
                                                                setDeciding({
                                                                    leave: row,
                                                                    status: 'approved',
                                                                })
                                                            }
                                                        >
                                                            Approve
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                setDeciding({
                                                                    leave: row,
                                                                    status: 'rejected',
                                                                })
                                                            }
                                                        >
                                                            Reject
                                                        </Button>
                                                    </>
                                                )}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </TabsContent>

                    <TabsContent value="departments">
                        <div className="rounded-xl border bg-card shadow-xs">
                            <div className="flex justify-end border-b p-3">
                                <Button
                                    size="sm"
                                    onClick={() => setAddingDepartment(true)}
                                >
                                    <Plus />
                                    New department
                                </Button>
                            </div>
                            <ul className="divide-y text-sm">
                                {departments.map((row) => (
                                    <li
                                        key={row.id}
                                        className="flex justify-between gap-3 p-4"
                                    >
                                        <span>
                                            <span className="font-medium">
                                                {row.name}
                                            </span>
                                            {row.description && (
                                                <span className="block text-xs text-muted-foreground">
                                                    {row.description}
                                                </span>
                                            )}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {row.staff_count} staff
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </TabsContent>
                </Tabs>
            </div>

            <StaffDialog
                member={editing}
                onClose={() => setEditing(undefined)}
                {...props}
            />
            <ShiftDialog cell={shiftCell} onClose={() => setShiftCell(null)} />
            <AttendanceDialog
                cell={attendanceCell}
                onClose={() => setAttendanceCell(null)}
                statuses={options.attendance}
            />
            <LeaveDialog
                open={requestingLeave}
                onClose={() => setRequestingLeave(false)}
                staff={activeStaff}
                leaveTypes={options.leaveTypes}
            />
            <DecisionDialog
                decision={deciding}
                onClose={() => setDeciding(null)}
            />
            <DepartmentDialog
                open={addingDepartment}
                onClose={() => setAddingDepartment(false)}
            />
        </>
    );
}

function StaffDialog({
    member: staff,
    onClose,
    departments,
    accounts,
    options,
}: Props & { member: Staff | null | undefined; onClose: () => void }) {
    const blank = {
        first_name: '',
        last_name: '',
        employee_number: '',
        department_id: '',
        job_title: '',
        employment_type: 'full_time',
        hire_date: '',
        email: '',
        phone: '',
        emergency_contact_name: '',
        emergency_contact_phone: '',
        user_id: '',
        is_active: true,
    };
    const form = useForm(blank);
    const { data, setData, errors } = form;
    const accountOptions = [
        ...(staff?.account ? [staff.account] : []),
        ...accounts,
    ].map((account) => ({
        value: String(account.id),
        label: `${account.name} (${account.email})`,
    }));

    useEffect(() => {
        if (staff !== undefined) {
            form.clearErrors();
            form.setData(
                staff
                    ? {
                          first_name: staff.first_name,
                          last_name: staff.last_name,
                          employee_number: staff.employee_number,
                          department_id: String(staff.department_id),
                          job_title: staff.job_title,
                          employment_type: staff.employment_type,
                          hire_date: staff.hire_date,
                          email: staff.email ?? '',
                          phone: staff.phone ?? '',
                          emergency_contact_name:
                              staff.emergency_contact_name ?? '',
                          emergency_contact_phone:
                              staff.emergency_contact_phone ?? '',
                          user_id: staff.user_id ? String(staff.user_id) : '',
                          is_active: staff.is_active,
                      }
                    : blank,
            );
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [staff]);

    function submit() {
        const opts = {
            preserveScroll: true,
            onSuccess: onClose,
            onError: notifyValidationFailed,
        };

        if (staff) {
            form.put(admin.staff.update.url(staff.id), opts);
        } else {
            form.post(admin.staff.store.url(), opts);
        }
    }

    return (
        <FormDialog
            open={staff !== undefined}
            onOpenChange={(open) => !open && onClose()}
            title={staff ? `Edit ${staff.name}` : 'Add staff member'}
            size="lg"
            processing={form.processing}
            onSubmit={submit}
        >
            <FormErrorSummary errors={errors} />
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="First name"
                    htmlFor="st-first"
                    error={errors.first_name}
                    required
                >
                    <Input
                        id="st-first"
                        value={data.first_name}
                        onChange={(event) =>
                            setData('first_name', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Last name"
                    htmlFor="st-last"
                    error={errors.last_name}
                    required
                >
                    <Input
                        id="st-last"
                        value={data.last_name}
                        onChange={(event) =>
                            setData('last_name', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Employee number"
                    htmlFor="st-number"
                    error={errors.employee_number}
                    required
                >
                    <Input
                        id="st-number"
                        value={data.employee_number}
                        onChange={(event) =>
                            setData('employee_number', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Department"
                    htmlFor="st-dept"
                    error={errors.department_id}
                    required
                >
                    <OptionSelect
                        id="st-dept"
                        value={data.department_id}
                        options={departments.map((row) => ({
                            value: String(row.id),
                            label: row.name,
                        }))}
                        onChange={(value) => setData('department_id', value)}
                    />
                </Field>
                <Field
                    label="Job title"
                    htmlFor="st-title"
                    error={errors.job_title}
                    required
                >
                    <Input
                        id="st-title"
                        value={data.job_title}
                        onChange={(event) =>
                            setData('job_title', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Employment"
                    htmlFor="st-type"
                    error={errors.employment_type}
                    required
                >
                    <OptionSelect
                        id="st-type"
                        value={data.employment_type}
                        options={options.employmentTypes}
                        onChange={(value) => setData('employment_type', value)}
                    />
                </Field>
                <Field
                    label="Hire date"
                    htmlFor="st-hire"
                    error={errors.hire_date}
                    required
                >
                    <Input
                        id="st-hire"
                        type="date"
                        value={data.hire_date}
                        onChange={(event) =>
                            setData('hire_date', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="System account"
                    htmlFor="st-user"
                    error={errors.user_id}
                >
                    <OptionSelect
                        id="st-user"
                        value={data.user_id}
                        placeholder="No login"
                        options={accountOptions}
                        onChange={(value) => setData('user_id', value)}
                    />
                </Field>
                <Field label="Email" htmlFor="st-email" error={errors.email}>
                    <Input
                        id="st-email"
                        type="email"
                        value={data.email}
                        onChange={(event) =>
                            setData('email', event.target.value)
                        }
                    />
                </Field>
                <Field label="Phone" htmlFor="st-phone" error={errors.phone}>
                    <Input
                        id="st-phone"
                        value={data.phone}
                        onChange={(event) =>
                            setData('phone', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Emergency contact"
                    htmlFor="st-ec-name"
                    error={errors.emergency_contact_name}
                >
                    <Input
                        id="st-ec-name"
                        value={data.emergency_contact_name}
                        onChange={(event) =>
                            setData(
                                'emergency_contact_name',
                                event.target.value,
                            )
                        }
                    />
                </Field>
                <Field
                    label="Emergency phone"
                    htmlFor="st-ec-phone"
                    error={errors.emergency_contact_phone}
                >
                    <Input
                        id="st-ec-phone"
                        value={data.emergency_contact_phone}
                        onChange={(event) =>
                            setData(
                                'emergency_contact_phone',
                                event.target.value,
                            )
                        }
                    />
                </Field>
            </div>
            <div className="flex items-center gap-2">
                <Switch
                    id="st-active"
                    checked={data.is_active}
                    onCheckedChange={(checked) => setData('is_active', checked)}
                />
                <Label htmlFor="st-active">Currently employed</Label>
            </div>
        </FormDialog>
    );
}

function ShiftDialog({
    cell,
    onClose,
}: {
    cell: { staff: Staff; date: string } | null;
    onClose: () => void;
}) {
    const form = useForm({
        staff_profile_id: '',
        shift_date: '',
        starts_at: '07:00',
        ends_at: '15:00',
        role: '',
        notes: '',
    });

    useEffect(() => {
        if (cell) {
            form.clearErrors();
            form.setData({
                ...form.data,
                staff_profile_id: String(cell.staff.id),
                shift_date: cell.date,
                role: cell.staff.job_title,
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [cell]);

    return (
        <FormDialog
            open={cell !== null}
            onOpenChange={(open) => !open && onClose()}
            title={cell ? `Shift for ${cell.staff.name}` : 'Shift'}
            description={
                cell
                    ? `${formatDate(cell.date)}. A shift ending before it starts runs overnight.`
                    : undefined
            }
            submitLabel="Add shift"
            processing={form.processing}
            onSubmit={() =>
                form.post(admin.staff.shifts.store.url(), {
                    preserveScroll: true,
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Starts"
                    htmlFor="sh-start"
                    error={form.errors.starts_at}
                    required
                >
                    <Input
                        id="sh-start"
                        type="time"
                        value={form.data.starts_at}
                        onChange={(event) =>
                            form.setData('starts_at', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Ends"
                    htmlFor="sh-end"
                    error={form.errors.ends_at}
                    required
                >
                    <Input
                        id="sh-end"
                        type="time"
                        value={form.data.ends_at}
                        onChange={(event) =>
                            form.setData('ends_at', event.target.value)
                        }
                    />
                </Field>
            </div>
            <Field
                label="Role on shift"
                htmlFor="sh-role"
                error={form.errors.role}
            >
                <Input
                    id="sh-role"
                    value={form.data.role}
                    onChange={(event) =>
                        form.setData('role', event.target.value)
                    }
                />
            </Field>
            {form.errors.shift_date && (
                <p className="text-sm text-destructive">
                    {form.errors.shift_date}
                </p>
            )}
        </FormDialog>
    );
}

function AttendanceDialog({
    cell,
    onClose,
    statuses,
}: {
    cell: { staff: Staff; date: string } | null;
    onClose: () => void;
    statuses: Option[];
}) {
    const form = useForm({
        staff_profile_id: '',
        work_date: '',
        status: 'present',
        clock_in_at: '',
        clock_out_at: '',
        notes: '',
    });

    useEffect(() => {
        if (cell) {
            form.clearErrors();
            form.setData({
                staff_profile_id: String(cell.staff.id),
                work_date: cell.date,
                status: 'present',
                clock_in_at: '',
                clock_out_at: '',
                notes: '',
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [cell]);

    return (
        <FormDialog
            open={cell !== null}
            onOpenChange={(open) => !open && onClose()}
            title={cell ? `Attendance · ${cell.staff.name}` : 'Attendance'}
            description={cell ? formatDate(cell.date) : undefined}
            processing={form.processing}
            onSubmit={() =>
                form.post(admin.staff.attendance.store.url(), {
                    preserveScroll: true,
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <Field
                label="Status"
                htmlFor="at-status"
                error={form.errors.status}
                required
            >
                <OptionSelect
                    id="at-status"
                    value={form.data.status}
                    options={statuses}
                    onChange={(value) => form.setData('status', value)}
                />
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Clock in"
                    htmlFor="at-in"
                    error={form.errors.clock_in_at}
                >
                    <Input
                        id="at-in"
                        type="time"
                        value={form.data.clock_in_at}
                        onChange={(event) =>
                            form.setData('clock_in_at', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Clock out"
                    htmlFor="at-out"
                    error={form.errors.clock_out_at}
                >
                    <Input
                        id="at-out"
                        type="time"
                        value={form.data.clock_out_at}
                        onChange={(event) =>
                            form.setData('clock_out_at', event.target.value)
                        }
                    />
                </Field>
            </div>
            <Field label="Notes" htmlFor="at-notes" error={form.errors.notes}>
                <Input
                    id="at-notes"
                    value={form.data.notes}
                    onChange={(event) =>
                        form.setData('notes', event.target.value)
                    }
                />
            </Field>
            {form.errors.work_date && (
                <p className="text-sm text-destructive">
                    {form.errors.work_date}
                </p>
            )}
        </FormDialog>
    );
}

function LeaveDialog({
    open,
    onClose,
    staff,
    leaveTypes,
}: {
    open: boolean;
    onClose: () => void;
    staff: Staff[];
    leaveTypes: Option[];
}) {
    const form = useForm({
        staff_profile_id: '',
        type: 'annual',
        start_date: '',
        end_date: '',
        reason: '',
    });

    useEffect(() => {
        if (open) {
            form.reset();
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    return (
        <FormDialog
            open={open}
            onOpenChange={(next) => !next && onClose()}
            title="Record a leave request"
            processing={form.processing}
            onSubmit={() =>
                form.post(admin.staff.leave.store.url(), {
                    preserveScroll: true,
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <Field
                label="Staff member"
                htmlFor="lv-staff"
                error={form.errors.staff_profile_id}
                required
            >
                <OptionSelect
                    id="lv-staff"
                    value={form.data.staff_profile_id}
                    options={staff.map((member) => ({
                        value: String(member.id),
                        label: member.name,
                    }))}
                    onChange={(value) =>
                        form.setData('staff_profile_id', value)
                    }
                />
            </Field>
            <Field
                label="Type"
                htmlFor="lv-type"
                error={form.errors.type}
                required
            >
                <OptionSelect
                    id="lv-type"
                    value={form.data.type}
                    options={leaveTypes}
                    onChange={(value) => form.setData('type', value)}
                />
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="From"
                    htmlFor="lv-start"
                    error={form.errors.start_date}
                    required
                >
                    <Input
                        id="lv-start"
                        type="date"
                        value={form.data.start_date}
                        onChange={(event) =>
                            form.setData('start_date', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="To"
                    htmlFor="lv-end"
                    error={form.errors.end_date}
                    required
                >
                    <Input
                        id="lv-end"
                        type="date"
                        min={form.data.start_date || undefined}
                        value={form.data.end_date}
                        onChange={(event) =>
                            form.setData('end_date', event.target.value)
                        }
                    />
                </Field>
            </div>
            <Field
                label="Reason"
                htmlFor="lv-reason"
                error={form.errors.reason}
            >
                <Textarea
                    id="lv-reason"
                    rows={2}
                    value={form.data.reason}
                    onChange={(event) =>
                        form.setData('reason', event.target.value)
                    }
                />
            </Field>
        </FormDialog>
    );
}

function DecisionDialog({
    decision,
    onClose,
}: {
    decision: { leave: Leave; status: 'approved' | 'rejected' } | null;
    onClose: () => void;
}) {
    const form = useForm({ status: '', decision_note: '' });

    useEffect(() => {
        if (decision) {
            form.clearErrors();
            form.setData({ status: decision.status, decision_note: '' });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [decision]);

    const rejecting = decision?.status === 'rejected';

    return (
        <FormDialog
            open={decision !== null}
            onOpenChange={(open) => !open && onClose()}
            title={rejecting ? 'Reject leave' : 'Approve leave'}
            description={
                decision
                    ? `${decision.leave.staff}, ${formatDate(decision.leave.start_date)} – ${formatDate(decision.leave.end_date)}`
                    : undefined
            }
            submitLabel={rejecting ? 'Reject' : 'Approve'}
            processing={form.processing}
            onSubmit={() =>
                decision &&
                form.post(admin.staff.leave.decide.url(decision.leave.id), {
                    preserveScroll: true,
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <Field
                label="Note"
                htmlFor="ld-note"
                error={form.errors.decision_note}
                required={rejecting}
            >
                <Textarea
                    id="ld-note"
                    rows={2}
                    value={form.data.decision_note}
                    onChange={(event) =>
                        form.setData('decision_note', event.target.value)
                    }
                />
            </Field>
            {form.errors.status && (
                <p className="text-sm text-destructive">{form.errors.status}</p>
            )}
        </FormDialog>
    );
}

function DepartmentDialog({
    open,
    onClose,
}: {
    open: boolean;
    onClose: () => void;
}) {
    const form = useForm({ name: '', description: '' });

    useEffect(() => {
        if (open) {
            form.reset();
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    return (
        <FormDialog
            open={open}
            onOpenChange={(next) => !next && onClose()}
            title="New department"
            processing={form.processing}
            onSubmit={() =>
                form.post(admin.staff.departments.store.url(), {
                    preserveScroll: true,
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <Field
                label="Name"
                htmlFor="dp-name"
                error={form.errors.name}
                required
            >
                <Input
                    id="dp-name"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                />
            </Field>
            <Field
                label="Description"
                htmlFor="dp-description"
                error={form.errors.description}
            >
                <Input
                    id="dp-description"
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                />
            </Field>
        </FormDialog>
    );
}

StaffIndex.layout = {
    breadcrumbs: [{ title: 'Staff', href: admin.staff.index() }],
};

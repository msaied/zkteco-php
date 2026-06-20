# ZKTeco PHP

A PHP package for talking to ZKTeco biometric attendance devices. It ports the
pyzk socket protocol first, and is shaped so an ADMS push adapter can be added
later over the same domain.

## Language

**Device**:
A ZKTeco biometric terminal (fingerprint / face / card) — the physical thing.
Each adapter addresses it by a different handle: the ZK protocol by host+port (the
`Device` value object, which we initiate to), ADMS by serial number (a Registered
device, which initiates to us).
_Avoid_: terminal, machine, clock, reader.

**Registered device**:
An ADMS device that has dialed in and been recorded, keyed by its serial number.
Holds its Protocol generation, capabilities, last-seen time, per-table Stamps,
and Device status. The ADMS counterpart to the ZK protocol's host/port
connection — not the same as the `Device` value object.
_Avoid_: node, client, endpoint, SN (that's just its key).

**Device status**:
A Registered device's approval state — **pending**, **approved**, or
**blocked**. Approval is separate from admission: a device can be recorded
(visible) while its attendance is held until approved. This is what lets the
server "accept all, but choose which to add" without trusting unapproved data.
_Avoid_: state (too vague), enabled/disabled (that's the ZK device-lock concept).

**Stamp**:
A per-table, per-device watermark in ADMS marking how far a device has uploaded
(e.g. ATTLOG, OPERLOG, BIODATA). The device resumes from its last stamp, so the
server must persist it. ZK protocol has no equivalent.
_Avoid_: cursor, offset, checkpoint, marker.

**Queued command**:
An instruction the server enqueues for a Registered device to run on its next
poll, acknowledged later. Distinct from a ZK-protocol command, which is a binary
opcode sent and answered synchronously over the socket.
_Avoid_: command (ambiguous with the ZK opcode), job, task, message.

**ZK protocol**:
The proprietary binary request/response protocol spoken over a TCP/UDP socket on
port 4370. The protocol pyzk implements and this package's primary target.
_Avoid_: SOAP, socket protocol.

**ADMS**:
The *family* of ZKTeco's HTTP push protocols: the device POSTs data to a server
and polls it for queued commands. Inverse of the ZK protocol — the device
initiates. Spans two generations (see Protocol generation). A planned second
adapter.
_Avoid_: iclock (that names the URL path, not the protocol).

**Protocol generation**:
Which generation of ADMS a given device speaks: **legacy** (`cdata` text,
per-type FP/FACE records) or **PUSH SDK** (a superset adding the BIODATA and
RTLOG tables and a dedicated registry endpoint). Negotiated per device at
registration, not a global setting.
_Avoid_: version (too vague on its own), firmware.

**PUSH SDK**:
ZKTeco's name for the newer ADMS generation — a superset of legacy ADMS, not a
separate protocol. A value of Protocol generation within the ADMS family.
_Avoid_: treating "PUSH" as a synonym for ADMS itself.

**User**:
A person enrolled on a device. Carries a device-local `uid` (the record slot
index, 1..N) and a `user_id` (the human-facing employee number string). These
two are distinct and must not be conflated.
_Avoid_: employee, member, person.

**Attendance record**:
A single clock event — who punched, when, by what verify method, and the in/out
status.
_Avoid_: log, transaction, event, punch (informal).

**Template**:
A biometric enrollment (fingerprint or face) belonging to a User. A User may
have several.
_Avoid_: fingerprint (too narrow), biometric, finger.

**Operation log entry**:
One audited action from a device's operation log — an operator enrolling or
deleting a User, changing a setting, a power cycle. In ADMS the device uploads
these in its OPERLOG channel (which the legacy generation multiplexes with its
USERINFO). The ZK protocol surfaces equivalent activity only as live events.
_Avoid_: audit log, event (clashes with Attendance record), oplog.

**Attendance photo**:
A photo a device captured at a punch and uploaded out of band (ADMS ATTPHOTO),
separately from the Attendance record it belongs to. The image bytes are opaque
and uninterpreted, tied back to the punch by User and capture time.
_Avoid_: snapshot, picture, image (on its own), user photo (that's USERPIC).

**Privilege**:
A User's access level on the device — regular user, enroller/manager, or admin.
Modeled as an enum.
_Avoid_: role, status, permission.

**Verify mode**:
On an Attendance record, *how* identity was confirmed — password, fingerprint,
face, or card. Corresponds to pyzk's confusingly named `status` field.
_Avoid_: status, method, type.

**Punch state**:
On an Attendance record, *what the punch means* — check-in, check-out,
break-out/in, or overtime-in/out. Corresponds to pyzk's `punch` field.
_Avoid_: status, type, direction.

**Comm key**:
The numeric communication password that guards a ZK-protocol socket session.
Distinct from a User's own password.
_Avoid_: password, comm password, comkey.

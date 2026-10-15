"""Build the landscape Pre-Proposal Defense flowchart (.drawio).

Layout: 5 columns x 4 rows so it fits a landscape Word page (9 x 6.5 in usable)
with every label at 20px. Black and white, orthogonal connectors attached to shapes.
Run:  python3 build_flowchart_landscape.py out.drawio
"""
import sys
from xml.sax.saxutils import escape

FONT = 20
C = [None, 190, 442, 700, 980, 1250]  # column centres c1..c5 (wider where two diamonds meet)
Y1, Y2, Y3, Y4 = 175, 395, 595, 745               # row centres

BASE = (f"html=1;whiteSpace=wrap;fontSize={FONT};fontFamily=Helvetica;fillColor=#FFFFFF;"
        "strokeColor=#000000;strokeWidth=2;fontColor=#000000;")
SHAPES = {
    "rect": "rounded=0;" + BASE,
    "term": "rounded=1;arcSize=50;" + BASE,
    "dia": "rhombus;shape=rhombus;perimeter=rhombusPerimeter;" + BASE,
}
EDGE = (f"edgeStyle=orthogonalEdgeStyle;rounded=0;orthogonalLoop=1;html=1;endArrow=block;endFill=1;"
        f"endSize=8;strokeColor=#000000;strokeWidth=2;fontColor=#000000;fontSize={FONT};"
        "fontFamily=Helvetica;jumpStyle=none;labelBackgroundColor=none;")

RW, RH = 210, 90     # process box
SW, SH = 180, 70     # small "Team Revises Title" box
DW, DH = 230, 180    # decision
TW, TH = 130, 50     # Start / End

nodes = {}


def node(nid, kind, label, cx, cy, w, h):
    nodes[nid] = (kind, label, cx, cy, w, h)


node("start", "term", "Start", C[1], 75, TW, TH)
node("p1", "rect", "1. Student Team<br>Formation", C[1], Y1, RW, RH)
node("p2", "rect", "2. Team Creates<br>Pre-Proposal", C[2], Y1, RW, RH)
node("d1", "dia", "Does CaPSA<br>recommend the<br>pre-proposal?", C[3], Y1, DW, DH)
node("dexp", "dia", "Does the team<br>already have<br>experts?", C[4], Y1, DW, DH)
node("p3", "rect", "3. Team Finds Experts<br>(Adviser, Consultant,<br>Grammarian)", C[5], Y1, RW, RH)

node("pmaj", "rect", "Team Revises<br>Title", C[1], Y2, SW, SH)
node("dadv", "dia", "Does the<br>adviser approve<br>the title?", C[2], Y2, DW, DH)
node("p3r", "rect", "Team Revises<br>Title", C[4], Y2, SW, SH)
node("d2", "dia", "Does the<br>adviser approve<br>the title?", C[5], Y2, DW, DH)

node("p7", "rect", "Team Defends<br>Pre-Proposal", C[1], Y3, RW, RH)
node("p6", "rect", "6. CaPSA Schedules<br>Pre-Proposal<br>Defense", C[2], Y3, RW, RH)
node("p5", "rect", "5. CaPSA Assigns<br>Panels", C[3] - 8, Y3, 190, RH)
node("d3", "dia", "Does the team<br>have an adviser<br>and a consultant?", C[4], Y3, 260, 200)
node("p4", "rect", "4. Team Submits Role<br>Acceptance Forms<br>to CaPSA", C[5], Y3, RW, RH)

node("d4", "dia", "Defense<br>result?", C[1], Y4, 200, 130)
node("end", "term", "End", C[3], Y4, TW, TH)

# Side anchors (x, y) as fractions of the shape box.
R, L, T, B = (1, 0.5), (0, 0.5), (0.5, 0), (0.5, 1)

LANE_L_IN, LANE_L_OUT = 50, 20                    # left-margin lanes
LANE_R = C[5] + 138                                # right-margin lane

edges = []


def edge(src, dst, exit_, entry, label="", points=(), offset=(0, 0), align="left"):
    edges.append((src, dst, exit_, entry, label, points, offset, align))


edge("start", "p1", B, T)
edge("p1", "p2", R, L)
edge("p2", "d1", R, L)
edge("d1", "p2", T, T, "No", [(C[3], 70), (C[2], 70)], (8, -22))
edge("d1", "dexp", R, L, "Yes", offset=(-36, -42))
edge("dexp", "p3", R, L, "No", offset=(-28, -42))
edge("dexp", "dadv", B, R, "Yes", [(C[4], 276), (C[3], 276), (C[3], Y2)], (8, 2))
edge("p3", "d2", B, T)
edge("d2", "p3r", L, R, "No", offset=(-34, -30))
edge("p3r", "d2", (0.78, 0), T, "", [(C[4] + 50, 296), (C[5], 296)])
edge("d2", "p4", B, T, "Yes", offset=(8, 4))
edge("p4", "d3", L, R)
edge("d3", "p5", L, R, "Yes", offset=(4, -46))
edge("d3", "p3", B, R, "No", [(C[4], 716), (LANE_R, 716), (LANE_R, Y1)], (12, 0))
edge("p5", "p6", L, R)
edge("p6", "p7", L, R)
edge("p7", "d4", B, T)
edge("d4", "end", R, L, "Passed or Passed with<br>Minor Revision", offset=(40, -42))
edge("d4", "pmaj", L, L, "Major<br>Revision", [(LANE_L_IN, Y4), (LANE_L_IN, Y2)], (-38, -52))
edge("d4", "p2", B, (0.32, 1), "Redefense with<br>New Title",
     [(C[1], 835), (LANE_L_OUT, 835), (LANE_L_OUT, 268), (C[2] - 40, 268)], (14, 6))
edge("pmaj", "dadv", R, L)
edge("dadv", "pmaj", T, T, "No", [(C[2], 292), (C[1], 292)], (8, -20))
edge("dadv", "p6", B, T, "Yes", offset=(8, 4))


def build() -> str:
    cells = ['<mxCell id="0"/>', '<mxCell id="1" parent="0"/>']
    title_w = 700
    cells.append(
        f'<mxCell id="title" value="Pre-Proposal Defense Flowchart" style="text;html=1;align=center;'
        f'verticalAlign=middle;fillColor=none;strokeColor=none;fontSize={FONT};fontStyle=1;'
        f'fontFamily=Helvetica;fontColor=#000000;" vertex="1" parent="1"><mxGeometry x="{(C[1] + C[5]) / 2 - title_w / 2}" '
        f'y="0" width="{title_w}" height="34" as="geometry"/></mxCell>')
    for nid, (kind, label, cx, cy, w, h) in nodes.items():
        cells.append(
            f'<mxCell id="{nid}" value="{escape(label)}" style="{SHAPES[kind]}" vertex="1" parent="1">'
            f'<mxGeometry x="{cx - w / 2}" y="{cy - h / 2}" width="{w}" height="{h}" as="geometry"/></mxCell>')
    for i, (src, dst, ex, en, label, points, offset, align) in enumerate(edges):
        style = (EDGE + f"exitX={ex[0]};exitY={ex[1]};exitDx=0;exitDy=0;"
                 f"entryX={en[0]};entryY={en[1]};entryDx=0;entryDy=0;align={align};")
        pts = "".join(f'<mxPoint x="{x}" y="{y}"/>' for x, y in points)
        off = f'<mxPoint x="{offset[0]}" y="{offset[1]}" as="offset"/>' if label else ""
        cells.append(
            f'<mxCell id="e{i}" value="{escape(label)}" style="{style}" edge="1" parent="1" source="{src}" target="{dst}">'
            f'<mxGeometry x="-1" relative="1" as="geometry"><Array as="points">{pts}</Array>{off}</mxGeometry></mxCell>')
    return ('<mxfile host="app.diagrams.net"><diagram id="flow-landscape" name="Pre-Proposal Defense (Landscape)">'
            '<mxGraphModel dx="1400" dy="900" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" '
            'fold="1" page="1" pageScale="1" pageWidth="1500" pageHeight="900" math="0" shadow="0"><root>'
            + "".join(cells) + "</root></mxGraphModel></diagram></mxfile>\n")


if __name__ == "__main__":
    with open(sys.argv[1], "w", encoding="utf-8") as f:
        f.write(build())
